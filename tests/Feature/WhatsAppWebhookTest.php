<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret  = 'test_secret';
    private string $verifyToken = 'test_verify_token';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.whatsapp.app_secret'   => $this->secret,
            'services.whatsapp.verify_token' => $this->verifyToken,
        ]);
    }

    // ─── Verification handshake ───────────────────────────────────────────────

    public function test_verify_returns_challenge_with_correct_token(): void
    {
        $response = $this->get('/webhooks/whatsapp?' . http_build_query([
            'hub_mode'         => 'subscribe',
            'hub_verify_token' => $this->verifyToken,
            'hub_challenge'    => 'CHALLENGE_STRING',
        ]));

        $response->assertStatus(200)->assertSee('CHALLENGE_STRING');
    }

    public function test_verify_returns_403_with_wrong_token(): void
    {
        $response = $this->get('/webhooks/whatsapp?' . http_build_query([
            'hub_mode'         => 'subscribe',
            'hub_verify_token' => 'wrong_token',
            'hub_challenge'    => 'CHALLENGE_STRING',
        ]));

        $response->assertStatus(403);
    }

    // ─── Signature verification ───────────────────────────────────────────────

    public function test_post_with_invalid_signature_returns_401(): void
    {
        $response = $this->postJson(
            '/webhooks/whatsapp',
            $this->sampleInboundPayload('601112345678'),
            ['X-Hub-Signature-256' => 'sha256=invalidsignature']
        );

        $response->assertStatus(401);
    }

    public function test_post_with_valid_signature_dispatches_job(): void
    {
        Bus::fake();

        $payload = $this->sampleInboundPayload('601112345678');
        $body    = json_encode($payload);

        $response = $this->call(
            'POST',
            '/webhooks/whatsapp',
            [],
            [],
            [],
            ['HTTP_X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', $body, $this->secret)],
            $body
        );

        $response->assertStatus(200);
        Bus::assertDispatched(ProcessWhatsAppWebhook::class);
    }

    // ─── Inbound message processing ──────────────────────────────────────────

    public function test_inbound_message_creates_contact_and_logs_message(): void
    {
        Notification::fake();
        $this->mockSender(null);

        User::factory()->create();

        $job = new ProcessWhatsAppWebhook($this->sampleInboundPayload('601112345678', 'Hello'));
        $job->handle(
            app(\App\Services\WhatsAppPayloadParser::class),
            app(WhatsAppSender::class)
        );

        $this->assertDatabaseHas('contacts', ['whatsapp_phone' => '601112345678']);
        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_message_id' => 'wamid.TEST001',
            'direction'           => 'inbound',
            'message_text'        => 'Hello',
        ]);
    }

    public function test_inbound_message_creates_deal_for_new_contact(): void
    {
        Notification::fake();
        $this->mockSender(null);

        User::factory()->create();

        $job = new ProcessWhatsAppWebhook($this->sampleInboundPayload('601119999999'));
        $job->handle(
            app(\App\Services\WhatsAppPayloadParser::class),
            app(WhatsAppSender::class)
        );

        $contact = Contact::where('whatsapp_phone', '601119999999')->firstOrFail();

        $this->assertDatabaseHas('deals', [
            'contact_id' => $contact->id,
            'stage'      => 'New Lead',
            'status'     => 'open',
        ]);
    }

    // ─── Deduplication ────────────────────────────────────────────────────────

    public function test_duplicate_message_id_is_not_logged_twice(): void
    {
        Notification::fake();
        $this->mockSender(null);

        User::factory()->create();

        $payload = $this->sampleInboundPayload('601113333333');

        $job = new ProcessWhatsAppWebhook($payload);
        $job->handle(app(\App\Services\WhatsAppPayloadParser::class), app(WhatsAppSender::class));
        $job->handle(app(\App\Services\WhatsAppPayloadParser::class), app(WhatsAppSender::class));

        $this->assertSame(
            1,
            WhatsAppMessage::where('whatsapp_message_id', 'wamid.TEST001')->count()
        );
    }

    public function test_second_message_from_existing_contact_does_not_create_new_deal(): void
    {
        Notification::fake();
        $this->mockSender(null);

        User::factory()->create();

        // First message
        $first = new ProcessWhatsAppWebhook($this->sampleInboundPayload('601114444444', 'Hi', 'wamid.MSG001'));
        $first->handle(app(\App\Services\WhatsAppPayloadParser::class), app(WhatsAppSender::class));

        // Second message (different message ID, same phone)
        $second = new ProcessWhatsAppWebhook($this->sampleInboundPayload('601114444444', 'Still me', 'wamid.MSG002'));
        $second->handle(app(\App\Services\WhatsAppPayloadParser::class), app(WhatsAppSender::class));

        $contact = Contact::where('whatsapp_phone', '601114444444')->firstOrFail();

        $this->assertSame(1, Deal::where('contact_id', $contact->id)->count());
        $this->assertSame(2, WhatsAppMessage::where('contact_id', $contact->id)->where('direction', 'inbound')->count());
    }

    // ─── Status updates ───────────────────────────────────────────────────────

    public function test_status_update_payload_does_not_create_contact(): void
    {
        $job = new ProcessWhatsAppWebhook($this->sampleStatusPayload('wamid.OUTBOUND01', 'delivered'));
        $job->handle(
            app(\App\Services\WhatsAppPayloadParser::class),
            app(WhatsAppSender::class)
        );

        $this->assertSame(0, Contact::count());
    }

    public function test_status_update_patches_existing_message_status(): void
    {
        $contact = Contact::create(['name' => 'Test Contact', 'whatsapp_phone' => '601115555555']);
        WhatsAppMessage::create([
            'contact_id'          => $contact->id,
            'channel'             => 'whatsapp',
            'direction'           => 'outbound',
            'whatsapp_message_id' => 'wamid.OUTBOUND01',
            'message_type'        => 'text',
            'message_text'        => 'Hi there!',
            'status'              => 'sent',
            'timestamp'           => now(),
            'raw_payload'         => [],
        ]);

        $job = new ProcessWhatsAppWebhook($this->sampleStatusPayload('wamid.OUTBOUND01', 'read'));
        $job->handle(app(\App\Services\WhatsAppPayloadParser::class), app(WhatsAppSender::class));

        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_message_id' => 'wamid.OUTBOUND01',
            'status'              => 'read',
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function mockSender(?string $returnId): void
    {
        $mock = $this->createMock(WhatsAppSender::class);
        $mock->method('sendText')->willReturn($returnId);
        $this->app->instance(WhatsAppSender::class, $mock);
    }

    private function sampleInboundPayload(
        string $phone,
        string $text      = 'Hello',
        string $messageId = 'wamid.TEST001'
    ): array {
        return [
            'object' => 'whatsapp_business_account',
            'entry'  => [[
                'id'      => '0',
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata'          => ['phone_number_id' => '123'],
                        'contacts'          => [[
                            'profile' => ['name' => 'Test User'],
                            'wa_id'   => $phone,
                        ]],
                        'messages' => [[
                            'from'      => $phone,
                            'id'        => $messageId,
                            'timestamp' => (string) time(),
                            'type'      => 'text',
                            'text'      => ['body' => $text],
                        ]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ];
    }

    private function sampleStatusPayload(string $messageId, string $status): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry'  => [[
                'id'      => '0',
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata'          => ['phone_number_id' => '123'],
                        'statuses'          => [[
                            'id'           => $messageId,
                            'status'       => $status,
                            'timestamp'    => (string) time(),
                            'recipient_id' => '601115555555',
                        ]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ];
    }
}
