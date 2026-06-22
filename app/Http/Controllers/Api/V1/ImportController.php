<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactCategory;
use App\Models\ContactIncharge;
use App\Models\ContactIndustry;
use App\Models\ContactStatus;
use App\Models\ContactType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ImportController extends Controller
{
    private array $canonicalAliases = [
        'status' => [
            // Raw
            'raw' => 'Raw', 'new' => 'Raw', 'untouched' => 'Raw', 'uncontacted' => 'Raw',
            'raw new' => 'Raw',
            // Active
            'active' => 'Active', 'contacted' => 'Active',
            // Existing Client
            'existing' => 'Existing Client', 'existing client' => 'Existing Client',
            'existing clients' => 'Existing Client', 'exist' => 'Existing Client', 'client' => 'Existing Client',
            // Potential — including territory-prefixed variants from old CRM
            'potential' => 'Potential', 'potentials' => 'Potential',
            'prospect' => 'Potential', 'prospects' => 'Potential',
            'kltg potential' => 'Potential', 'jhtg potential' => 'Potential',
            'kltg - sabah' => 'Potential', 'sabah potential' => 'Potential',
            // Rejected
            'rejected' => 'Rejected', 'reject' => 'Rejected', 'no' => 'Rejected',
            // On Going
            'on going' => 'On Going', 'ongoing' => 'On Going', 'on-going' => 'On Going',
            'in progress' => 'On Going', 'in-progress' => 'On Going',
            // Others
            'supplier' => 'Supplier', 'agency' => 'Agency',
        ],
        'client_type' => [
            'a1' => 'A1', 'a 1' => 'A1', 'a2' => 'A2', 'a 2' => 'A2', 'a3' => 'A3', 'a 3' => 'A3',
            'on going' => 'On Going', 'ongoing' => 'On Going', 'on-going' => 'On Going',
            'reject' => 'Reject', 'rejected' => 'Reject',
        ],
        'industry' => [
            'f&b' => 'Food & Beverage', 'fnb' => 'Food & Beverage', 'food' => 'Food & Beverage',
            'restaurant' => 'Food & Beverage', 'cafe' => 'Food & Beverage',
            'hotel' => 'Hospitality', 'hotels' => 'Hospitality', 'resort' => 'Hospitality',
            'hospitality' => 'Hospitality',
            'accommodation' => 'Hospitality', 'accomodation' => 'Hospitality', // old CRM typo
            'retail' => 'Retail', 'retails' => 'Retail', 'shopping' => 'Retail',
            'edu' => 'Education', 'education' => 'Education', 'school' => 'Education',
            'university' => 'Education', 'college' => 'Education',
            'manufacturing' => 'Manufacturing', 'factory' => 'Manufacturing',
            'industrial & manufacturing' => 'Manufacturing',
            'healthcare' => 'Healthcare', 'medical' => 'Healthcare', 'hospital' => 'Healthcare',
            'it' => 'Technology', 'tech' => 'Technology', 'technology' => 'Technology', 'software' => 'Technology',
            'real estate' => 'Real Estate', 'property' => 'Real Estate', 'properties' => 'Real Estate',
            'finance' => 'Finance', 'banking' => 'Finance', 'insurance' => 'Finance',
            'media' => 'Media & Entertainment', 'entertainment' => 'Media & Entertainment',
            'travel' => 'Travel & Tourism', 'tourism' => 'Travel & Tourism', 'tour' => 'Travel & Tourism',
            'construction' => 'Construction', 'building' => 'Construction', 'contractor' => 'Construction',
            'automotive' => 'Automotive', 'car' => 'Automotive', 'vehicle' => 'Automotive',
            'transportation & automotive' => 'Transportation & Automotive',
            'transportation & automative' => 'Transportation & Automotive', // old CRM typo
            'agriculture' => 'Agriculture', 'farming' => 'Agriculture', 'farm' => 'Agriculture',
            'logistics' => 'Logistics', 'transport' => 'Logistics', 'shipping' => 'Logistics',
            'government' => 'Government', 'govt' => 'Government',
            'ngo' => 'Non-Profit', 'non-profit' => 'Non-Profit', 'nonprofit' => 'Non-Profit',
            'legal' => 'Legal', 'law' => 'Legal', 'lawyer' => 'Legal',
            'telecom' => 'Telecommunications', 'telecommunications' => 'Telecommunications', 'telco' => 'Telecommunications',
        ],
    ];

    private function normalize(string $raw, string $field): string
    {
        $lower = strtolower(trim($raw));
        return $this->canonicalAliases[$field][$lower] ?? trim($raw);
    }

    // Strip the "(N) " prefix produced by old CRM multi-PIC exports, e.g. "(1) John" → "John"
    private function stripPicPrefix(string $value): string
    {
        return trim(preg_replace('/^\(\d+\)\s*/', '', $value));
    }

    // True for placeholder strings the old CRM inserts when no real data exists
    private function isPlaceholder(string $value): bool
    {
        return in_array(strtolower(trim($value)), [
            '', 'null', 'n/a', 'na', '-', 'none',
            'no address saved', 'no remark', 'no email',
            'noemail@gmail.com',
        ]);
    }

    public function preview(Request $request)
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            return response()->json(['error' => 'PhpSpreadsheet not installed. Run: composer require phpoffice/phpspreadsheet'], 422);
        }

        $request->validate(['file' => 'required|file|max:20480']);

        ini_set('memory_limit', '512M');

        try {
            $path = $request->file('file')->store('imports', 'local');
            if (!$path) {
                return response()->json(['error' => 'Failed to store uploaded file. Check storage permissions.'], 422);
            }

            $fullPath = Storage::disk('local')->path($path);

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

            // Auto-detect header row — the row with the most filled cells in the first 20 rows
            $headerRow = 1;
            $maxFilled = 0;
            for ($r = 1; $r <= min(20, $sheet->getHighestDataRow()); $r++) {
                $filled = 0;
                for ($c = 1; $c <= $highestCol; $c++) {
                    $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                    if (trim((string)$sheet->getCell($letter . $r)->getValue()) !== '') $filled++;
                }
                if ($filled > $maxFilled) { $maxFilled = $filled; $headerRow = $r; }
            }

            $headers = [];
            for ($c = 1; $c <= $highestCol; $c++) {
                $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                $val = trim((string)$sheet->getCell($letter . $headerRow)->getValue());
                if ($val !== '') $headers[$letter] = $val;
            }

            return response()->json([
                'temp_path'  => $path,
                'header_row' => $headerRow,
                'data_start' => $headerRow + 1,
                'headers'    => $headers,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to read file: ' . $e->getMessage()], 422);
        }
    }

    public function process(Request $request)
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            return response()->json(['error' => 'PhpSpreadsheet not installed. Run: composer require phpoffice/phpspreadsheet'], 422);
        }

        $allowedFields = [
            'name', 'address', 'remark', 'date_created',
            'status', 'client_type', 'industry', 'category',
            'assigned_user', 'pic_name', 'email', 'phone_mobile',
        ];

        $request->validate([
            'temp_path'  => ['required', 'string', 'regex:/^imports\/[a-zA-Z0-9\/._-]+$/'],
            'data_start' => 'required|integer|min:1|max:1000',
            'mapping'    => 'required|array|max:50',
            'mapping.*'  => ['nullable', 'string', Rule::in($allowedFields)],
        ]);

        ini_set('memory_limit', '512M');

        $fullPath = Storage::disk('local')->path($request->input('temp_path'));
        if (!file_exists($fullPath)) {
            return response()->json(['error' => 'Uploaded file not found.'], 422);
        }

        $mapping   = $request->input('mapping');
        $dataStart = (int)$request->input('data_start');

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
        $sheet       = $spreadsheet->getActiveSheet();
        $highestRow  = $sheet->getHighestDataRow();

        // Resolve name column
        $nameCol = null;
        foreach ($mapping as $col => $field) {
            if ($field === 'name') { $nameCol = $col; break; }
        }
        if (!$nameCol) {
            return response()->json(['error' => 'No column mapped to Company Name.'], 422);
        }

        // Pre-fetch existing contact names for duplicate detection
        $existing = Contact::pluck('name')->map(fn($n) => strtolower(trim($n)))->flip()->all();

        // In-memory FK maps with lowercase keys for case-insensitive lookup
        // (DB unique indexes are case-insensitive; PHP array lookups are case-sensitive)
        $toLower = fn($id, $n) => [strtolower(trim($n)) => $id];
        $statusMap   = ContactStatus::pluck('id', 'name')->mapWithKeys($toLower)->all();
        $typeMap     = ContactType::pluck('id', 'name')->mapWithKeys($toLower)->all();
        $industryMap = ContactIndustry::pluck('id', 'name')->mapWithKeys($toLower)->all();
        $categoryMap = ContactCategory::pluck('id', 'name')->mapWithKeys($toLower)->all();
        // Build case-insensitive user map: lowercase(name) → id
        $userMap = User::pluck('id', 'name')->mapWithKeys(
            fn($id, $name) => [strtolower(trim($name)) => $id]
        )->all();

        // Pre-compute PIC column letters once — not per row
        $picNameCol  = array_search('pic_name',     $mapping) ?: null;
        $picEmailCol = array_search('email',        $mapping) ?: null;
        $picPhoneCol = array_search('phone_mobile', $mapping) ?: null;
        $picFields   = ['pic_name', 'email', 'phone_mobile'];

        $imported = 0;
        $skipped  = 0;
        $failed   = 0;
        $errors   = [];

        DB::beginTransaction();
        try {
            for ($row = $dataStart; $row <= $highestRow; $row++) {
                try {
                    $name = mb_substr(trim((string)$sheet->getCell($nameCol . $row)->getValue()), 0, 255);
                    if ($name === '' || $this->isPlaceholder($name)) continue;

                    if (isset($existing[strtolower($name)])) { $skipped++; continue; }

                    $data        = ['name' => $name];
                    $dateCreated = null;

                    foreach ($mapping as $col => $field) {
                        if ($field === 'name' || $field === '' || in_array($field, $picFields)) continue;

                        $cellObj = $sheet->getCell($col . $row);
                        $cellVal = $cellObj->getValue();

                        // Handle Excel date serial numbers for date fields
                        if ($field === 'date_created' && is_numeric($cellVal) && $cellVal > 1) {
                            try {
                                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$cellVal);
                                $dateCreated = \Carbon\Carbon::instance($dt)->format('Y-m-d H:i:s');
                            } catch (\Exception $e) {}
                            continue;
                        }

                        $raw = trim((string)$cellVal);
                        if ($this->isPlaceholder($raw)) continue;

                        switch ($field) {
                            case 'date_created':
                                try {
                                    $dateCreated = \Carbon\Carbon::parse($raw)->format('Y-m-d H:i:s');
                                } catch (\Exception $e) {}
                                break;
                            case 'address':
                                $data['address'] = mb_substr($raw, 0, 255);
                                break;
                            case 'remark':
                                $data['remark'] = mb_substr($raw, 0, 800);
                                break;
                            case 'status':
                                $val  = $this->normalize($raw, 'status');
                                $lval = strtolower($val);
                                if (!isset($statusMap[$lval])) {
                                    $statusMap[$lval] = ContactStatus::firstOrCreate(['name' => $val])->id;
                                }
                                $data['status_id'] = $statusMap[$lval];
                                break;
                            case 'client_type':
                                $val  = $this->normalize($raw, 'client_type');
                                $lval = strtolower($val);
                                if (!isset($typeMap[$lval])) {
                                    $typeMap[$lval] = ContactType::firstOrCreate(['name' => $val])->id;
                                }
                                $data['type_id'] = $typeMap[$lval];
                                break;
                            case 'industry':
                                $val  = $this->normalize($raw, 'industry');
                                $lval = strtolower($val);
                                if (!isset($industryMap[$lval])) {
                                    $industryMap[$lval] = ContactIndustry::firstOrCreate(['name' => $val])->id;
                                }
                                $data['industry_id'] = $industryMap[$lval];
                                break;
                            case 'category':
                                $lval = strtolower($raw);
                                if (!isset($categoryMap[$lval])) {
                                    $categoryMap[$lval] = ContactCategory::firstOrCreate(['name' => $raw])->id;
                                }
                                $data['category_id'] = $categoryMap[$lval];
                                break;
                            case 'assigned_user':
                                $data['user_id'] = $userMap[strtolower(trim($raw))] ?? null;
                                break;
                        }
                    }

                    $contact = Contact::create($data);

                    if ($dateCreated) {
                        $contact->timestamps = false;
                        $contact->created_at = $dateCreated;
                        $contact->updated_at = $dateCreated;
                        $contact->save();
                    }

                    // PIC — strip "(N) " prefix left by old CRM multi-PIC export format
                    $picName  = $picNameCol  ? $this->stripPicPrefix((string)$sheet->getCell($picNameCol  . $row)->getValue()) : '';
                    $picEmail = $picEmailCol ? $this->stripPicPrefix((string)$sheet->getCell($picEmailCol . $row)->getValue()) : '';
                    $picPhone = $picPhoneCol ? $this->stripPicPrefix((string)$sheet->getCell($picPhoneCol . $row)->getValue()) : '';

                    if ($this->isPlaceholder($picPhone)) $picPhone = '';
                    if ($this->isPlaceholder($picEmail) || !filter_var($picEmail, FILTER_VALIDATE_EMAIL)) $picEmail = '';

                    if ($picName || $picEmail || $picPhone) {
                        ContactIncharge::create([
                            'contact_id'   => $contact->id,
                            'name'         => $picName  ? mb_substr($picName,  0, 255) : null,
                            'email'        => $picEmail ?: null,
                            'phone_mobile' => $picPhone ? mb_substr($picPhone, 0,  50) : null,
                        ]);
                    }

                    $existing[strtolower($name)] = true;
                    $imported++;
                } catch (\Exception $e) {
                    $failed++;
                    if (count($errors) < 20) {
                        $errors[] = ['row' => $row, 'name' => $name ?? '?', 'reason' => $e->getMessage()];
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Storage::disk('local')->delete($request->input('temp_path'));
            return response()->json(['error' => $e->getMessage()], 500);
        }

        Storage::disk('local')->delete($request->input('temp_path'));

        return response()->json(['imported' => $imported, 'skipped' => $skipped, 'failed' => $failed, 'errors' => $errors]);
    }
}
