<?php

namespace App\Console\Commands;

use App\Models\AdvertisingProduct;
use Illuminate\Console\Command;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

class ImportSiteCatalogue extends Command
{
    protected $signature = 'site-availability:import-catalogue
        {manifest : Path to a JSON manifest built from a region catalogue deck}
        {--dry-run : Show what would happen without writing to the database or disk}
        {--match-radius=250 : Match an existing row within this many metres of the manifest coordinate}';

    protected $description = 'Load site photos, map screenshots, and coordinates from a region catalogue manifest into Site Availability (advertising_products), matching existing rows by coordinate or creating new ones';

    public function handle(): int
    {
        $path = $this->argument('manifest');
        if (!is_file($path)) {
            $this->error("Manifest not found: {$path}");
            return self::FAILURE;
        }

        $sites = json_decode(file_get_contents($path), true);
        if (!is_array($sites)) {
            $this->error('Manifest is not valid JSON.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $radius = (float) $this->option('match-radius');

        $existing = AdvertisingProduct::all(['id', 'site_name', 'coordinate', 'site_photo', 'site_map_photo']);
        $parsedExisting = $existing->map(function ($row) {
            return ['row' => $row, 'coord' => $this->parseCoord($row->coordinate)];
        })->filter(fn ($e) => $e['coord'] !== null)->values();

        // Two different manifest sites can legitimately land within the match
        // radius of the same existing row (an exact coordinate duplicate in
        // the source deck, or two genuinely distinct sites a few dozen metres
        // apart). Naively matching each site to its own nearest row lets a
        // later site silently steal — and overwrite the photo of — a row a
        // closer site already claimed. So this resolves matches globally: every
        // (site, row) pair within radius is a candidate, sorted by distance,
        // and assigned greedily so each existing row is claimed at most once.
        $candidates = [];
        foreach ($sites as $i => $site) {
            $coord = $this->parseCoord($site['coordinate'] ?? null);
            if (!$coord) {
                continue;
            }
            foreach ($parsedExisting as $e) {
                $d = $this->distanceMeters($coord, $e['coord']);
                if ($d < $radius) {
                    $candidates[] = ['site' => $i, 'row' => $e['row']->id, 'dist' => $d];
                }
            }
        }
        usort($candidates, fn ($a, $b) => $a['dist'] <=> $b['dist']);

        $siteMatch = [];
        $rowClaimed = [];
        foreach ($candidates as $c) {
            if (isset($siteMatch[$c['site']]) || isset($rowClaimed[$c['row']])) {
                continue;
            }
            $siteMatch[$c['site']] = ['row_id' => $c['row'], 'dist' => $c['dist']];
            $rowClaimed[$c['row']] = $c['site'];
        }

        $existingById = $existing->keyBy('id');

        $updated = 0;
        $created = 0;
        $skippedNoCoord = 0;

        foreach ($sites as $i => $site) {
            $coord = $this->parseCoord($site['coordinate'] ?? null);
            if (!$coord) {
                $this->warn("SKIP {$site['site_code']} — unparseable coordinate: " . ($site['coordinate'] ?? '(none)'));
                $skippedNoCoord++;
                continue;
            }

            $best = null;
            $bestDist = null;
            if (isset($siteMatch[$i])) {
                $best = $existingById[$siteMatch[$i]['row_id']];
                $bestDist = $siteMatch[$i]['dist'];
            }

            $fields = [
                'site_code'         => $site['site_code'],
                'site_name'         => $site['site_name'],
                'state_city'        => $site['state_city'],
                'size'              => $site['size'],
                'coordinate'        => $site['coordinate'],
                'illumination'      => $site['illumination'],
                'nearest_landmarks' => $site['nearest_landmarks'],
                'contact_name'      => $site['contact_name'],
                'contact_mobile'    => $site['contact_mobile'],
            ];

            if ($best) {
                $label = "UPDATE  {$site['site_code']} -> id={$best->id} (\"{$best->site_name}\", " . round($bestDist) . "m)";
                if ($dryRun) {
                    $this->line("[dry-run] {$label}");
                } else {
                    $best->fill($fields);
                    if (!empty($site['photo_path'])) {
                        $this->replacePhoto($best, 'site_photo', $site['photo_path']);
                    }
                    if (!empty($site['map_path'])) {
                        $this->replacePhoto($best, 'site_map_photo', $site['map_path']);
                    }
                    $best->save();
                    $this->line($label);
                }
                $updated++;
            } else {
                $label = "CREATE  {$site['site_code']} (\"{$site['site_name']}\")";
                if ($dryRun) {
                    $this->line("[dry-run] {$label}");
                } else {
                    $product = new AdvertisingProduct(array_merge($fields, [
                        'status'       => 'Raw New',
                        'product_type' => $site['product_type'] ?? 'Temp Board',
                        'type'         => 'A1',
                        'is_pending'   => false,
                    ]));
                    $product->save();
                    if (!empty($site['photo_path'])) {
                        $this->replacePhoto($product, 'site_photo', $site['photo_path']);
                    }
                    if (!empty($site['map_path'])) {
                        $this->replacePhoto($product, 'site_map_photo', $site['map_path']);
                    }
                    $product->save();
                    $this->line($label);
                }
                $created++;
            }
        }

        $verb = $dryRun ? 'would be' : '';
        $this->info("Done — {$updated} matched/updated, {$created} new {$verb}, {$skippedNoCoord} skipped (bad coordinate).");

        return self::SUCCESS;
    }

    private function replacePhoto(AdvertisingProduct $product, string $column, string $localPath): void
    {
        if (!is_file($localPath)) {
            $this->warn("  ! file missing on disk, skipped {$column}: {$localPath}");
            return;
        }

        if ($product->$column && Storage::disk('public')->exists($product->$column)) {
            Storage::disk('public')->delete($product->$column);
        }

        $stored = Storage::disk('public')->putFile("advertising_products/{$product->id}", new File($localPath));
        $product->$column = $stored;
    }

    private function parseCoord(?string $c): ?array
    {
        if (!$c) return null;
        $parts = array_map('trim', explode(',', $c));
        if (count($parts) < 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) return null;
        return [(float) $parts[0], (float) $parts[1]];
    }

    private function distanceMeters(array $a, array $b): float
    {
        $latAvg = deg2rad(($a[0] + $b[0]) / 2);
        $dx = deg2rad($a[1] - $b[1]) * cos($latAvg);
        $dy = deg2rad($a[0] - $b[0]);
        return sqrt($dx * $dx + $dy * $dy) * 6371000;
    }
}
