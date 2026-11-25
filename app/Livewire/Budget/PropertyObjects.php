<?php

namespace App\Livewire\Budget;

use App\Models\Household;
use App\Models\PropertyObject;
use Livewire\Component;

class PropertyObjects extends Component
{
    public function render()
    {
        $household = $this->household();

        return view('livewire.budget.property-objects', [
            'objects' => PropertyObject::query()
                ->where('household_id', $household->id)
                ->withCount(['debts', 'planLines'])
                ->orderBy('name')
                ->orderBy('id')
                ->get(),
        ])->title(__('Объекты — Family Money'));
    }

    private function household(): Household
    {
        return auth()->user()->currentHousehold();
    }
}
