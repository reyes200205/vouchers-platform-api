<?php

declare(strict_types=1);

namespace App\Services\Reconciliations;

use App\Enums\ImportStatus;
use App\Models\BankTransaction;
use App\Models\BankTransactionImport;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

final class ImportBankDepositsService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, Branch $branch, array $data): BankTransactionImport
    {
        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $data['file'];
        $fileHash = md5_file($file->getRealPath());

        if (BankTransactionImport::query()->where('file_hash', $fileHash)->exists()) {
            abort(422, 'El archivo ya fue importado anteriormente.');
        }

        $rows = $this->parseRows($file);

        if ($rows === []) {
            abort(422, 'El archivo no contiene filas con datos válidos.');
        }

        return DB::transaction(function () use ($user, $branch, $file, $fileHash, $rows): BankTransactionImport {
            $errors = [];
            $created = 0;

            foreach ($rows as $index => $row) {
                try {
                    $this->validateRow($row);
                    $this->createTransaction($branch, $row);
                    $created++;
                } catch (\Throwable $exception) {
                    $errors[] = ['row' => $index + 2, 'error' => $exception->getMessage()];
                }
            }

            if ($created === 0) {
                abort(422, 'Ninguna fila del archivo pudo ser importada.');
            }

            return BankTransactionImport::query()->create([
                'filename' => $file->getClientOriginalName(),
                'file_hash' => $fileHash,
                'imported_by_user_id' => $user->id,
                'branch_id' => $branch->id,
                'row_count' => count($rows),
                'error_count' => count($errors),
                'errors_json' => $errors === [] ? null : $errors,
                'status' => $errors === [] ? ImportStatus::COMPLETADO : ImportStatus::PARCIAL,
                'imported_at' => now(),
            ]);
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseRows(mixed $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'csv') {
            return $this->parseCsv($file);
        }

        return $this->parseSpreadsheet($file);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseCsv(mixed $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $rows = [];

        if ($handle === false) {
            abort(422, 'No se pudo leer el archivo.');
        }

        $headers = fgetcsv($handle);
        $headerMap = $this->buildHeaderMap($headers);

        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) < count($headers ?? [])) {
                $line = array_pad($line, count($headers ?? []), null);
            }

            $row = [];
            foreach (array_keys($headerMap) as $index) {
                $row[$headerMap[$index]] = $line[$index] ?? null;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseSpreadsheet(mixed $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = [];

        foreach ($sheet->getRowIterator(1) as $rowIndex => $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = $cell->getValue();
            }

            if ($rowIndex === 1) {
                $headerMap = $this->buildHeaderMap($cells);
                continue;
            }

            if (!isset($headerMap)) {
                continue;
            }

            $mapped = [];
            foreach (array_keys($headerMap) as $index) {
                $value = $cells[$index] ?? null;

                if ($value instanceof \DateTimeInterface) {
                    $value = $value->format('Y-m-d');
                } elseif (is_numeric($value) && $headerMap[$index] === 'fecha') {
                    try {
                        $value = Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
                    } catch (\Throwable) {
                        // keep raw value
                    }
                }

                $mapped[$headerMap[$index]] = $value;
            }

            $rows[] = $mapped;
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>|null  $headers
     * @return array<int, string>
     */
    private function buildHeaderMap(?array $headers): array
    {
        $map = [
            'fecha' => 'fecha',
            'date' => 'fecha',
            'referencia' => 'referencia',
            'reference' => 'referencia',
            'referencia_pago' => 'referencia',
            'concepto' => 'concepto',
            'descripcion' => 'concepto',
            'description' => 'concepto',
            'detalle' => 'concepto',
            'importe' => 'importe',
            'monto' => 'importe',
            'amount' => 'importe',
            'deposito' => 'importe',
            'deposit' => 'importe',
        ];

        $headerMap = [];

        foreach ($headers ?? [] as $index => $header) {
            $normalized = strtolower(trim((string) $header));

            if (isset($map[$normalized])) {
                $headerMap[$index] = $map[$normalized];
            }
        }

        if (!isset($headerMap[0]) && isset($headers[0]) && !in_array(strtolower(trim((string) $headers[0])), $map, true)) {
            $headerMap = [0 => 'fecha', 1 => 'referencia', 2 => 'concepto', 3 => 'importe'];
        }

        return $headerMap;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function validateRow(array $row): void
    {
        $amount = $this->parseAmount($row['importe'] ?? null);

        if ($amount === null || $amount <= 0) {
            throw new \RuntimeException('El importe debe ser un número mayor a cero.');
        }

        $date = $this->parseDate($row['fecha'] ?? null);

        if ($date === null) {
            throw new \RuntimeException('La fecha es inválida.');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createTransaction(Branch $branch, array $row): void
    {
        $reference = trim((string) ($row['referencia'] ?? '')) ?: null;

        BankTransaction::query()->create([
            'reference' => $reference,
            'transaction_date' => $this->parseDate($row['fecha']),
            'transaction_time' => null,
            'amount' => $this->parseAmount($row['importe']),
            'transaction_type' => 'DEPOSITO',
            'transaction_number' => $reference,
            'payer_name' => null,
            'raw_description' => trim((string) ($row['concepto'] ?? '')) ?: null,
        ]);
    }

    private function parseAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = (string) $value;
        $negative = str_starts_with($normalized, '-') || str_starts_with($normalized, '(');

        $cleaned = preg_replace('/[^0-9.,]/', '', $normalized);
        $cleaned = str_replace([',', ' '], '', $cleaned);
        $cleaned = preg_replace('/^\(|\)$/', '', $cleaned);

        if ($cleaned === '' || !is_numeric($cleaned)) {
            return null;
        }

        $amount = (float) $cleaned;

        return $negative ? -$amount : $amount;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::parse($value->format('Y-m-d'));
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value) && $value > 20000) {
            try {
                return Carbon::instance(Date::excelToDateTimeObject((float) $value));
            } catch (\Throwable) {
                return null;
            }
        }

        $text = trim((string) $value);

        try {
            return Carbon::parse($text)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}