<?php

namespace App\Support;

use App\Models\Debt;
use App\Models\Household;
use App\Models\PropertyContract;
use App\Models\SecretDocument;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteOwnAccount
{
    public function __invoke(User $user): void
    {
        $receipts = [];
        $contractDocuments = [];
        $vaultPaths = SecretDocument::query()->where('user_id', $user->id)->pluck('path')->all();

        DB::transaction(function () use ($user, &$receipts, &$contractDocuments): void {
            $households = $user->households()->get();

            foreach ($households as $household) {
                $someoneRemains = $household->users()->where('users.id', '!=', $user->id)->exists();

                if ($someoneRemains) {
                    continue;
                }

                $contractDocuments = array_merge($contractDocuments, PropertyContract::query()
                    ->whereHas('propertyObject', fn ($query) => $query->where('household_id', $household->id))
                    ->whereNotNull('document_path')
                    ->pluck('document_path')
                    ->all());
                $receipts = array_merge($receipts, $this->deleteHousehold($household));
            }

            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        if ($receipts !== []) {
            Storage::disk('public')->delete($receipts);
        }

        foreach ($contractDocuments as $path) {
            ContractDocuments::delete(is_string($path) ? $path : null);
        }

        foreach ($vaultPaths as $path) {
            VaultFiles::delete($path);
        }
    }

    /**
     * @return list<string>
     */
    private function deleteHousehold(Household $household): array
    {
        $receipts = Transaction::query()
            ->where('household_id', $household->id)
            ->whereNotNull('receipt_path')
            ->pluck('receipt_path')
            ->all();

        Debt::query()->where('household_id', $household->id)->delete();
        Transaction::query()->where('household_id', $household->id)->delete();
        Transfer::query()->where('household_id', $household->id)->delete();
        $household->delete();

        return $receipts;
    }
}
