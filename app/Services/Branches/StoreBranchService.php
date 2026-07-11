<?php

declare(strict_types=1);

namespace App\Services\Branches;

use App\Models\Address;
use App\Models\Branch;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

final class StoreBranchService
{
    /**
     * @param array{
     *     name: string,
     *     branch_code?: string|null,
     *     branch_type: string,
     *     manager_id?: int|null,
     *     country: string,
     *     state: string,
     *     city: string,
     *     address: string,
     *     postal_code: string
     * } $data
     */
    public function execute(array $data): Branch
    {
        return DB::transaction(static function () use ($data): Branch {
            $address = Address::create([
                'country' => $data['country'],
                'state' => $data['state'],
                'city' => $data['city'],
                'address' => $data['address'],
                'postal_code' => $data['postal_code'],
            ]);

            $branchData = Arr::only($data, ['name', 'branch_type', 'manager_id']);
            $branchData['branch_code'] = $data['branch_code'] ?? 'BR-' . strtoupper(Str::random(6));
            $branchData['address_id'] = $address->id;

            return Branch::create($branchData);
        });
    }
}
