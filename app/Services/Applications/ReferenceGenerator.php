<?php

namespace App\Services\Applications;

use Illuminate\Support\Facades\DB;
use LogicException;

class ReferenceGenerator
{
    /** Allocate inside the same transaction that creates the application. */
    public function next(): string
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Application references must be allocated in a transaction.');
        }
        $year = (int) now()->format('Y');
        DB::table('application_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);
        $sequence = DB::table('application_sequences')->where('year', $year)->lockForUpdate()->first();
        $number = $sequence->last_number + 1;
        DB::table('application_sequences')->where('year', $year)->update(['last_number' => $number]);

        return sprintf('EW-%d-%05d', $year, $number);
    }
}
