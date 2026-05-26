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

class ImportController extends Controller
{
    private array $canonicalAliases = [
        'status' => [
            'raw' => 'Raw', 'new' => 'Raw', 'untouched' => 'Raw', 'uncontacted' => 'Raw',
            'active' => 'Active', 'contacted' => 'Active',
            'existing' => 'Existing Client', 'existing client' => 'Existing Client',
            'existing clients' => 'Existing Client', 'exist' => 'Existing Client', 'client' => 'Existing Client',
            'potential' => 'Potential', 'potentials' => 'Potential', 'prospect' => 'Potential', 'prospects' => 'Potential',
            'rejected' => 'Rejected', 'reject' => 'Rejected', 'no' => 'Rejected',
            'on going' => 'On Going', 'ongoing' => 'On Going', 'on-going' => 'On Going',
            'in progress' => 'On Going', 'in-progress' => 'On Going',
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
            'hospitality' => 'Hospitality', 'accommodation' => 'Hospitality',
            'edu' => 'Education', 'education' => 'Education', 'school' => 'Education',
            'university' => 'Education', 'college' => 'Education',
            'retail' => 'Retail', 'shopping' => 'Retail',
            'manufacturing' => 'Manufacturing', 'factory' => 'Manufacturing',
            'healthcare' => 'Healthcare', 'medical' => 'Healthcare', 'hospital' => 'Healthcare',
            'it' => 'Technology', 'tech' => 'Technology', 'technology' => 'Technology', 'software' => 'Technology',
            'real estate' => 'Real Estate', 'property' => 'Real Estate', 'properties' => 'Real Estate',
            'finance' => 'Finance', 'banking' => 'Finance', 'insurance' => 'Finance',
            'media' => 'Media & Entertainment', 'entertainment' => 'Media & Entertainment',
            'travel' => 'Travel & Tourism', 'tourism' => 'Travel & Tourism', 'tour' => 'Travel & Tourism',
            'construction' => 'Construction', 'building' => 'Construction', 'contractor' => 'Construction',
            'automotive' => 'Automotive', 'car' => 'Automotive', 'vehicle' => 'Automotive',
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

    private function getOrCreate(string $model, string $value): ?int
    {
        $value = trim($value);
        if ($value === '') return null;
        $record = $model::firstOrCreate(['name' => $value]);
        return $record->id;
    }

    public function preview(Request $request)
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            return response()->json(['error' => 'PhpSpreadsheet not installed. Run: composer require phpoffice/phpspreadsheet'], 422);
        }

        $request->validate(['file' => 'required|file|max:20480']);

        try {
            $path = $request->file('file')->store('imports', 'local');
            if (!$path) {
                return response()->json(['error' => 'Failed to store uploaded file. Check storage permissions.'], 422);
            }

            $fullPath = Storage::disk('local')->path($path);

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

            // Auto-detect header row (row with most filled cells in first 20)
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

        $request->validate([
            'temp_path'  => 'required|string',
            'data_start' => 'required|integer|min:1',
            'mapping'    => 'required|array',
        ]);

        $fullPath = Storage::disk('local')->path($request->input('temp_path'));
        if (!file_exists($fullPath)) {
            return response()->json(['error' => 'Uploaded file not found.'], 422);
        }

        $mapping   = $request->input('mapping');   // col_letter => field_name
        $dataStart = (int)$request->input('data_start');

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();

        // Find name column
        $nameCol = null;
        foreach ($mapping as $col => $field) {
            if ($field === 'name') { $nameCol = $col; break; }
        }
        if (!$nameCol) {
            return response()->json(['error' => 'No column mapped to Company Name.'], 422);
        }

        // Pre-fetch existing names
        $existing = Contact::pluck('name')->map(fn($n) => strtolower(trim($n)))->flip()->all();

        // In-memory FK maps
        $statusMap   = ContactStatus::pluck('id', 'name')->all();
        $typeMap     = ContactType::pluck('id', 'name')->all();
        $industryMap = ContactIndustry::pluck('id', 'name')->all();
        $categoryMap = ContactCategory::pluck('id', 'name')->all();
        $userMap     = User::pluck('id', 'name')->all();

        $imported = 0;
        $skipped  = 0;

        DB::beginTransaction();
        try {
            for ($row = $dataStart; $row <= $highestRow; $row++) {
                $name = trim((string)$sheet->getCell($nameCol . $row)->getValue());
                if ($name === '') continue;

                if (isset($existing[strtolower($name)])) { $skipped++; continue; }

                $data = ['name' => $name];

                foreach ($mapping as $col => $field) {
                    if ($field === 'name' || $field === '') continue;
                    $val = trim((string)$sheet->getCell($col . $row)->getValue());
                    if ($val === '') continue;

                    switch ($field) {
                        case 'address': $data['address'] = $val; break;
                        case 'remark':  $data['remark'] = $val; break;
                        case 'status':
                            $val = $this->normalize($val, 'status');
                            if (!isset($statusMap[$val])) {
                                ContactStatus::create(['name' => $val]);
                                $statusMap = ContactStatus::pluck('id', 'name')->all();
                            }
                            $data['status_id'] = $statusMap[$val] ?? null;
                            break;
                        case 'client_type':
                            $val = $this->normalize($val, 'client_type');
                            if (!isset($typeMap[$val])) {
                                ContactType::create(['name' => $val]);
                                $typeMap = ContactType::pluck('id', 'name')->all();
                            }
                            $data['type_id'] = $typeMap[$val] ?? null;
                            break;
                        case 'industry':
                            $val = $this->normalize($val, 'industry');
                            if (!isset($industryMap[$val])) {
                                ContactIndustry::create(['name' => $val]);
                                $industryMap = ContactIndustry::pluck('id', 'name')->all();
                            }
                            $data['industry_id'] = $industryMap[$val] ?? null;
                            break;
                        case 'category':
                            if (!isset($categoryMap[$val])) {
                                ContactCategory::create(['name' => $val]);
                                $categoryMap = ContactCategory::pluck('id', 'name')->all();
                            }
                            $data['category_id'] = $categoryMap[$val] ?? null;
                            break;
                        case 'assigned_user':
                            if (!isset($userMap[$val])) {
                                User::create(['name' => $val, 'email' => strtolower(str_replace([' ', "'"], ['.', ''], $val)) . '@placeholder.com', 'password' => bcrypt('password')]);
                                $userMap = User::pluck('id', 'name')->all();
                            }
                            $data['user_id'] = $userMap[$val] ?? null;
                            break;
                    }
                }

                $contact = Contact::create($data);

                // PICs
                $picNameCol  = array_search('pic_name', $mapping);
                $picEmailCol = array_search('email', $mapping);
                $picPhoneCol = array_search('phone_mobile', $mapping);

                $picName  = $picNameCol  ? trim((string)$sheet->getCell($picNameCol  . $row)->getValue()) : '';
                $picEmail = $picEmailCol ? trim((string)$sheet->getCell($picEmailCol . $row)->getValue()) : '';
                $picPhone = $picPhoneCol ? trim((string)$sheet->getCell($picPhoneCol . $row)->getValue()) : '';

                if ($picName || $picEmail || $picPhone) {
                    ContactIncharge::create([
                        'contact_id'   => $contact->id,
                        'name'         => $picName ?: null,
                        'email'        => $picEmail ?: null,
                        'phone_mobile' => $picPhone ?: null,
                    ]);
                }

                $existing[strtolower($name)] = true;
                $imported++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Storage::disk('local')->delete($request->input('temp_path'));
            return response()->json(['error' => $e->getMessage()], 500);
        }

        Storage::disk('local')->delete($request->input('temp_path'));

        return response()->json(['imported' => $imported, 'skipped' => $skipped]);
    }
}
