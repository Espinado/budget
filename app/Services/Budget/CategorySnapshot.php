<?php

namespace App\Services\Budget;

use App\Models\Category;
use Illuminate\Support\Collection;

final readonly class CategorySnapshot
{
    /**
     * @param  Collection<int, \App\Models\PlanLine>  $lines
     * @param  Collection<int, \App\Models\Transaction>  $transactions
     */
    public function __construct(
        public Category $category,
        public int $planned,
        public int $actual,
        public Collection $lines,
        public Collection $transactions,
    ) {}

    public function variance(): int
    {
        return $this->actual - $this->planned;
    }

    public function percent(): int
    {
        if ($this->planned <= 0) {
            return $this->actual > 0 ? 100 : 0;
        }

        return (int) min(100, intdiv($this->actual * 100, $this->planned));
    }

    public function over(): bool
    {
        return $this->planned > 0 && $this->actual > $this->planned;
    }

    public function outstanding(): int
    {
        return LineRemainder::split($this->lines, $this->transactions)['outstanding'];
    }

    public function committed(): int
    {
        return LineRemainder::split($this->lines, $this->transactions)['committed'];
    }

    public function overspend(): int
    {
        return max(0, $this->committed() - $this->planned);
    }
}
