<?php

namespace Tests\Feature;

use App\Enums\CashflowType;
use App\Enums\DebtDirection;
use App\Enums\DebtKind;
use App\Livewire\Budget\PropertyObjectForm;
use App\Livewire\Budget\PropertyObjects;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\Debt;
use App\Models\DebtInstallment;
use App\Models\Household;
use App\Models\PlanLine;
use App\Models\PropertyContract;
use App\Models\PropertyObject;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PropertyObjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_links_existing_debt_and_plan_line_and_delete_waits_for_the_debt(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'Europe/Riga'));
        [$user, $household] = $this->family('Наша');
        $home = $this->category($household, 'Дом');
        $insurance = $this->category($household, 'Страхование');
        $credit = $this->category($household, 'Кредит');
        $period = $this->period($household);
        $utilities = PlanLine::query()->create([
            'period_id' => $period->id,
            'category_id' => $home->id,
            'title' => 'Коммунальные',
            'amount_cents' => 12_000,
        ]);
        $policy = PlanLine::query()->create([
            'period_id' => $period->id,
            'category_id' => $insurance->id,
            'title' => 'Страхование квартиры',
            'amount_cents' => 3_000,
        ]);
        $debt = Debt::query()->create([
            'household_id' => $household->id,
            'category_id' => $credit->id,
            'direction' => DebtDirection::Borrow,
            'kind' => DebtKind::Bank,
            'counterparty' => 'Luminor',
        ]);

        $categories = Category::query()->orderBy('id')->pluck('name', 'id')->all();
        $lineIds = PlanLine::query()->orderBy('id')->pluck('id')->all();
        $periodCount = BudgetPeriod::query()->count();

        Livewire::actingAs($user)
            ->test(PropertyObjectForm::class)
            ->assertSee('Luminor')
            ->assertSee('Дом')
            ->assertSee('Страхование')
            ->assertDontSee('Коммунальные')
            ->assertSee('Недвижимое имущество')
            ->assertSee('Движимое имущество')
            ->assertDontSee('Квартира или машина')
            ->set('name', 'Квартира')
            ->set('kind', 'apartment')
            ->set('label', 'Бривибас 1')
            ->set('debtIds', [$debt->id])
            ->set('categoryIds', [(string) $home->id, (string) $insurance->id])
            ->call('save')
            ->assertHasNoErrors();

        $object = PropertyObject::query()->firstOrFail();

        $this->assertSame('Квартира', $object->name);
        $this->assertSame('apartment', $object->kind->value);
        $this->assertSame('Бривибас 1', $object->label);
        $this->assertEqualsCanonicalizing([$debt->id], $object->debts()->pluck('debts.id')->all());
        $this->assertEqualsCanonicalizing([$utilities->id, $policy->id], $object->planLines()->pluck('plan_lines.id')->all());
        $freshNames = Category::query()->orderBy('id')->pluck('name', 'id')->all();
        foreach ($categories as $id => $name) {
            $this->assertSame($name, $freshNames[$id]);
        }
        $this->assertSame(1, collect($freshNames)->filter(fn ($name) => $name === 'Дом')->count());
        $this->assertSame(1, collect($freshNames)->filter(fn ($name) => $name === 'Квартира')->count());
        $this->assertSame($lineIds, PlanLine::query()->orderBy('id')->pluck('id')->all());
        $this->assertSame($periodCount, BudgetPeriod::query()->count());
        $this->assertSame($credit->id, (int) $debt->fresh()->category_id);

        Livewire::actingAs($user)
            ->test(PropertyObjectForm::class)
            ->assertSee('Уже привязано к другому объекту.')
            ->set('name', 'Машина')
            ->set('kind', 'car')
            ->set('label', 'AB-1234')
            ->set('debtIds', [$debt->id])
            ->set('categoryIds', [(string) $home->id])
            ->call('save')
            ->assertHasErrors(['debtIds', 'planLineIds']);

        $this->assertSame(1, PropertyObject::query()->count());
        $this->assertSame($lineIds, PlanLine::query()->orderBy('id')->pluck('id')->all());

        [$other] = $this->family('Чужая');

        $this->actingAs($other)
            ->get(route('objects.edit', $object))
            ->assertNotFound();

        $this->actingAs($other)
            ->get(route('objects.index'))
            ->assertOk()
            ->assertDontSee('Бривибас 1');

        Livewire::actingAs($other)
            ->test(PropertyObjectForm::class, ['propertyObject' => $object])
            ->assertStatus(404);

        $screen = Livewire::actingAs($user)
            ->test(PropertyObjectForm::class, ['propertyObject' => $object])
            ->assertSet('name', 'Квартира')
            ->assertSee('Luminor')
            ->assertSee('Дом')
            ->assertSee('Страхование')
            ->assertDontSee('Коммунальные')
            ->set('name', 'Квартира у моря')
            ->set('debtIds', [])
            ->set('categoryIds', [(string) $home->id])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Карточка сохранена.');

        $object->refresh();
        $this->assertSame('Квартира у моря', $object->name);
        $this->assertSame('Квартира у моря', $object->category->name);
        $this->assertSame(1, Category::query()->where('name', 'Квартира у моря')->count());
        $this->assertSame(0, $object->debts()->count());
        $this->assertSame([$utilities->id], $object->planLines()->pluck('plan_lines.id')->all());
        $this->assertTrue(PlanLine::query()->whereKey($policy->id)->exists());
        $this->assertTrue(Debt::query()->whereKey($debt->id)->exists());

        $screen
            ->set('debtIds', [$debt->id])
            ->call('save')
            ->assertHasNoErrors()
            ->call('delete')
            ->assertHasErrors('delete')
            ->assertSee('Нельзя удалить карточку, пока к ней привязан долг. Отвяжите долг и сохраните. Сам долг в бюджете останется.');

        $this->assertNotNull(PropertyObject::query()->find($object->id));
        $this->assertTrue($object->fresh()->debts()->whereKey($debt->id)->exists());

        $screen
            ->set('debtIds', [])
            ->call('save')
            ->assertHasNoErrors()
            ->call('delete')
            ->assertHasNoErrors()
            ->assertRedirect(route('objects.index'));

        $this->assertNull(PropertyObject::query()->find($object->id));
        $this->assertSame(0, DB::table('property_object_debts')->count());
        $this->assertSame(0, DB::table('property_object_plan_lines')->count());
        $this->assertTrue(PlanLine::query()->whereKey($utilities->id)->exists());
        $this->assertTrue(PlanLine::query()->whereKey($policy->id)->exists());
        $this->assertTrue(Debt::query()->whereKey($debt->id)->exists());
        $this->assertSame($credit->id, (int) $debt->fresh()->category_id);
        $freshNames = Category::query()->orderBy('id')->pluck('name', 'id')->all();
        foreach ($categories as $id => $name) {
            $this->assertSame($name, $freshNames[$id]);
        }
        $this->assertSame(1, collect($freshNames)->filter(fn ($name) => $name === 'Дом')->count());
        $this->assertSame(0, collect($freshNames)->filter(fn ($name) => $name === 'Квартира')->count());
        $this->assertSame(1, collect($freshNames)->filter(fn ($name) => $name === 'Квартира у моря')->count());
    }

    public function test_expense_category_is_one_checkbox_and_attaches_every_line(): void
    {
        $this->app->setLocale('ru');
        [$user, $household] = $this->family('Наша');
        $home = $this->category($household, 'Дом');
        $rent = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $home->id,
            'type' => CashflowType::Expense,
            'name' => 'Квартплата Прушу',
            'sort' => 2,
        ]);
        $cafeCategory = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $home->id,
            'type' => CashflowType::Expense,
            'name' => 'Кафе',
            'sort' => 3,
        ]);
        $income = Category::query()->create([
            'household_id' => $household->id,
            'type' => CashflowType::Income,
            'name' => 'Зарплата',
            'sort' => 4,
        ]);
        $rentIds = [];
        $novemberId = null;

        foreach ([
            ['2026-11-01', '2026-11-30', 19_529],
            ['2026-12-01', '2026-12-31', 20_000],
            ['2027-01-01', '2027-01-31', 20_000],
        ] as [$start, $end, $cents]) {
            $period = BudgetPeriod::query()->create([
                'household_id' => $household->id,
                'starts_on' => $start,
                'ends_on' => $end,
                'opening_balance_cents' => 0,
                'status' => 'open',
            ]);

            if ($start === '2026-11-01') {
                $novemberId = $period->id;
            }
            $rentIds[] = PlanLine::query()->create([
                'period_id' => $period->id,
                'category_id' => $rent->id,
                'title' => 'Квартплата Прушу',
                'amount_cents' => $cents,
            ])->id;
            PlanLine::query()->create([
                'period_id' => $period->id,
                'category_id' => $income->id,
                'title' => 'Зарплата',
                'amount_cents' => 100_000,
            ]);
        }

        $cafe = PlanLine::query()->create([
            'period_id' => $novemberId,
            'category_id' => $cafeCategory->id,
            'title' => 'Кафе',
            'amount_cents' => 1_500,
        ]);
        $beforeLines = PlanLine::query()->count();
        $beforeCategories = Category::query()->count();

        $screen = Livewire::actingAs($user)
            ->test(PropertyObjectForm::class)
            ->assertDontSee('Зарплата')
            ->assertDontSee('195,29')
            ->assertDontSee('€')
            ->assertDontSee('ноябрь')
            ->assertSee('Квартплата Прушу')
            ->assertSee('Кафе')
            ->assertSee('Дом');

        $html = $screen->html();
        $this->assertStringContainsString('data-plan-heading="'.$home->id.'"', $html);
        $this->assertStringNotContainsString('data-plan-category="'.$home->id.'"', $html);
        $this->assertStringContainsString('data-plan-category="'.$rent->id.'"', $html);
        $this->assertStringContainsString('data-plan-category="'.$cafeCategory->id.'"', $html);

        $screen->set('planSearch', 'Квартплата');
        $searched = $screen->html();
        $this->assertStringContainsString('data-plan-category="'.$rent->id.'"', $searched);
        $this->assertStringNotContainsString('data-plan-category="'.$cafeCategory->id.'"', $searched);
        $screen
            ->assertSee('Квартплата Прушу')
            ->assertSee('Дом')
            ->assertDontSee('Зарплата')
            ->set('planSearch', '')
            ->set('name', 'Прушу')
            ->set('kind', 'apartment')
            ->set('label', 'Прушу 1')
            ->set('categoryIds', [(string) $rent->id])
            ->call('save')
            ->assertHasNoErrors();

        $object = PropertyObject::query()->firstOrFail();

        $this->assertEqualsCanonicalizing($rentIds, $object->planLines()->pluck('plan_lines.id')->all());
        $this->assertSame($beforeLines, PlanLine::query()->count());
        $this->assertSame($beforeCategories + 1, Category::query()->count());
        $this->assertFalse($object->planLines()->where('title', 'Зарплата')->exists());
        $this->assertFalse($object->planLines()->whereKey($cafe->id)->exists());

        $blocked = Livewire::actingAs($user)->test(PropertyObjectForm::class);

        $this->assertStringNotContainsString('data-plan-category="'.$rent->id.'"', $blocked->html());
        $this->assertStringContainsString('data-plan-category-taken="'.$rent->id.'"', $blocked->html());
        $blocked->assertSee('Уже привязано к другому объекту.');
        $blocked->assertDontSee('195,29');

        Livewire::actingAs($user)
            ->test(PropertyObjectForm::class)
            ->set('name', 'Другое')
            ->set('kind', 'car')
            ->set('label', 'AA-1')
            ->set('categoryIds', [(string) $rent->id])
            ->call('save')
            ->assertHasErrors('planLineIds');

        $this->assertSame(1, PropertyObject::query()->count());
        $this->assertEqualsCanonicalizing($rentIds, $object->fresh()->planLines()->pluck('plan_lines.id')->all());

        Livewire::actingAs($user)
            ->test(PropertyObjectForm::class, ['propertyObject' => $object])
            ->set('categoryIds', [])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, $object->fresh()->planLines()->count());
        $this->assertSame(3, PlanLine::query()->where('category_id', $rent->id)->count());
        $this->assertSame($beforeLines, PlanLine::query()->count());
    }

    public function test_debt_schedule_category_stays_off_the_picker_and_save_does_not_add_a_line(): void
    {
        [$user, $household] = $this->family('Наша');
        $home = $this->category($household, 'Дом');
        $prusu = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $home->id,
            'type' => CashflowType::Expense,
            'name' => 'Прушу',
            'sort' => 2,
        ]);
        $rent = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $prusu->id,
            'type' => CashflowType::Expense,
            'name' => 'Квартплата Прушу',
            'sort' => 3,
        ]);
        $schedule = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $prusu->id,
            'type' => CashflowType::Expense,
            'name' => 'Luminor Bank',
            'sort' => 4,
        ]);
        $credit = $this->category($household, 'Кредит');
        $november = $this->period($household);
        $december = BudgetPeriod::query()->create([
            'household_id' => $household->id,
            'starts_on' => '2026-12-01',
            'ends_on' => '2026-12-31',
            'opening_balance_cents' => 0,
            'status' => 'open',
        ]);
        $rentLine = PlanLine::query()->create([
            'period_id' => $november->id,
            'category_id' => $rent->id,
            'title' => 'Квартплата Прушу',
            'amount_cents' => 20_000,
        ]);
        $novemberMortgage = PlanLine::query()->create([
            'period_id' => $november->id,
            'category_id' => $credit->id,
            'title' => 'Luminor Bank',
            'amount_cents' => 27_843,
        ]);
        $decemberMortgage = PlanLine::query()->create([
            'period_id' => $december->id,
            'category_id' => $schedule->id,
            'title' => 'Luminor Bank',
            'amount_cents' => 28_463,
        ]);
        PlanLine::query()->create([
            'period_id' => $december->id,
            'category_id' => $credit->id,
            'title' => 'Creditea',
            'amount_cents' => 5_312,
        ]);
        $debt = Debt::query()->create([
            'household_id' => $household->id,
            'category_id' => $credit->id,
            'direction' => DebtDirection::Borrow,
            'kind' => DebtKind::Bank,
            'counterparty' => 'Luminor Bank',
        ]);
        DebtInstallment::query()->create([
            'debt_id' => $debt->id,
            'sequence' => 1,
            'due_on' => '2026-11-10',
            'principal_before_cents' => 1_000_000,
            'principal_cents' => 27_843,
            'interest_cents' => 0,
            'other_cents' => 0,
            'fee_cents' => 0,
            'total_cents' => 27_843,
            'plan_line_id' => $novemberMortgage->id,
        ]);
        DebtInstallment::query()->create([
            'debt_id' => $debt->id,
            'sequence' => 2,
            'due_on' => '2026-12-10',
            'principal_before_cents' => 900_000,
            'principal_cents' => 28_463,
            'interest_cents' => 0,
            'other_cents' => 0,
            'fee_cents' => 0,
            'total_cents' => 28_463,
            'plan_line_id' => $decemberMortgage->id,
        ]);
        $beforeLines = PlanLine::query()->count();

        $screen = Livewire::actingAs($user)
            ->test(PropertyObjectForm::class)
            ->assertSee('Luminor Bank')
            ->assertSee('Квартплата Прушу')
            ->assertSee('Кредит')
            ->assertSee('Прушу');

        $html = $screen->html();
        $this->assertStringContainsString('data-plan-category="'.$rent->id.'"', $html);
        $this->assertStringContainsString('data-plan-category="'.$credit->id.'"', $html);
        $this->assertStringNotContainsString('data-plan-category="'.$schedule->id.'"', $html);
        $this->assertStringNotContainsString('data-plan-category-taken="'.$schedule->id.'"', $html);

        $screen
            ->set('name', 'Прушу')
            ->set('kind', 'apartment')
            ->set('label', 'Прушу 1')
            ->set('debtIds', [$debt->id])
            ->set('categoryIds', [(string) $rent->id])
            ->call('save')
            ->assertHasNoErrors();

        $object = PropertyObject::query()->firstOrFail();

        $this->assertSame($beforeLines, PlanLine::query()->count());
        $this->assertEqualsCanonicalizing(
            [$rentLine->id, $decemberMortgage->id],
            $object->planLines()->pluck('plan_lines.id')->all(),
        );
        $this->assertSame($credit->id, (int) $novemberMortgage->fresh()->category_id);
        $this->assertSame($schedule->id, (int) $decemberMortgage->fresh()->category_id);
        $this->assertSame($credit->id, (int) $debt->fresh()->category_id);

        Livewire::actingAs($user)
            ->test(PropertyObjectForm::class, ['propertyObject' => $object])
            ->set('categoryIds', [(string) $rent->id])
            ->set('debtIds', [$debt->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($beforeLines, PlanLine::query()->count());
        $this->assertEqualsCanonicalizing(
            [$rentLine->id, $decemberMortgage->id],
            $object->fresh()->planLines()->pluck('plan_lines.id')->all(),
        );
        $this->assertSame($credit->id, (int) $novemberMortgage->fresh()->category_id);

        Livewire::actingAs($user)
            ->test(PropertyObjectForm::class, ['propertyObject' => $object])
            ->set('categoryIds', [(string) $rent->id])
            ->set('debtIds', [])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([$rentLine->id], $object->fresh()->planLines()->pluck('plan_lines.id')->all());
        $this->assertSame($beforeLines, PlanLine::query()->count());
        $this->assertTrue(PlanLine::query()->whereKey($decemberMortgage->id)->exists());
        $this->assertSame($credit->id, (int) $novemberMortgage->fresh()->category_id);
        $this->assertSame($schedule->id, (int) $decemberMortgage->fresh()->category_id);
    }

    public function test_expense_contract_credit_and_category_place_stay_on_the_object(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'Europe/Riga'));
        [$user, $household] = $this->family('Наша');
        $home = $this->category($household, 'Дом');
        $rent = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $home->id,
            'type' => CashflowType::Expense,
            'name' => 'Квартплата Прушу',
            'sort' => 2,
        ]);
        $insurance = $this->category($household, 'Страхование');
        $credit = $this->category($household, 'Кредит');
        $november = $this->period($household);
        $rentLine = PlanLine::query()->create([
            'period_id' => $november->id,
            'category_id' => $rent->id,
            'title' => 'Квартплата Прушу',
            'amount_cents' => 20_000,
            'expected_on' => '2026-11-05',
        ]);
        $paid = PlanLine::query()->create([
            'period_id' => $november->id,
            'category_id' => $insurance->id,
            'title' => 'Страхование квартиры',
            'amount_cents' => 3_209,
            'expected_on' => '2026-11-12',
        ]);
        Transaction::query()->create([
            'household_id' => $household->id,
            'period_id' => $november->id,
            'category_id' => $insurance->id,
            'plan_line_id' => $paid->id,
            'type' => CashflowType::Expense,
            'amount_cents' => 3_209,
            'occurred_on' => '2026-11-12',
            'created_by' => $user->id,
        ]);
        $creditea = PlanLine::query()->create([
            'period_id' => $november->id,
            'category_id' => $credit->id,
            'title' => 'Creditea',
            'amount_cents' => 5_312,
        ]);
        $oldDebt = Debt::query()->create([
            'household_id' => $household->id,
            'category_id' => $credit->id,
            'direction' => DebtDirection::Borrow,
            'kind' => DebtKind::Bank,
            'counterparty' => 'Creditea',
        ]);
        DebtInstallment::query()->create([
            'debt_id' => $oldDebt->id,
            'sequence' => 1,
            'due_on' => '2026-11-20',
            'principal_before_cents' => 5_312,
            'principal_cents' => 5_312,
            'interest_cents' => 0,
            'other_cents' => 0,
            'fee_cents' => 0,
            'total_cents' => 5_312,
            'plan_line_id' => $creditea->id,
        ]);

        Livewire::actingAs($user)
            ->test(PropertyObjectForm::class)
            ->assertSet('placeMode', 'under')
            ->assertSet('parentCategoryId', $home->id)
            ->set('name', 'Прушу')
            ->set('kind', 'apartment')
            ->set('label', 'Прушу 1')
            ->call('save')
            ->assertHasNoErrors();

        $object = PropertyObject::query()->firstOrFail();
        $this->assertSame($home->id, (int) $object->category->parent_id);
        $this->assertSame(1, Category::query()->where('name', 'Дом')->count());
        $this->assertSame(1, Category::query()->where('name', 'Квартплата Прушу')->count());

        $card = Livewire::actingAs($user)
            ->test(PropertyObjectForm::class, ['propertyObject' => $object])
            ->set('expenseName', 'Корм')
            ->set('expenseAmount', '5,00')
            ->set('expenseDate', '2026-11-18')
            ->call('saveExpense')
            ->assertHasNoErrors();

        $feed = PlanLine::query()->where('title', 'Корм')->get();
        $this->assertCount(1, $feed);
        $this->assertSame($november->id, (int) $feed[0]->period_id);
        $this->assertSame($object->category->id, (int) $feed[0]->category->parent_id);
        $this->assertSame(0, PlanLine::query()->where('title', 'Корм')->where('period_id', '!=', $november->id)->count());
        $this->assertSame(0, DebtInstallment::query()->where('plan_line_id', $feed[0]->id)->count());

        $card
            ->set('contractTitle', 'ERGO')
            ->set('contractNote', 'полис 77')
            ->set('contractParts', [['amount' => '32,09', 'date' => '2026-11-12']])
            ->call('saveContract')
            ->assertHasNoErrors()
            ->assertSee('ERGO')
            ->assertSee('полис 77')
            ->assertSee('12.11.2026');

        $ergo = PlanLine::query()->where('title', 'ERGO')->get();
        $this->assertCount(1, $ergo);
        $this->assertSame($november->id, (int) $ergo[0]->period_id);
        $this->assertSame(3_209, (int) $ergo[0]->amount_cents);
        $this->assertSame($object->category->id, (int) $ergo[0]->category->parent_id);
        $this->assertNull(PlanLine::query()->where('title', 'полис 77')->first());
        $this->assertSame(0, DebtInstallment::query()->where('plan_line_id', $ergo[0]->id)->count());
        $this->assertSame('полис 77', PropertyContract::query()->where('title', 'ERGO')->value('note'));

        $card
            ->set('contractTitle', 'Госпошлина')
            ->set('contractNote', '')
            ->set('contractParts', [
                ['amount' => '10,00', 'date' => '2026-11-20'],
                ['amount' => '32,06', 'date' => '2027-06-15'],
            ])
            ->call('saveContract')
            ->assertHasNoErrors()
            ->assertSee('15.06.2027');

        $duty = PlanLine::query()->where('title', 'Госпошлина')->orderBy('expected_on')->get();
        $this->assertCount(2, $duty);
        $this->assertSame($november->id, (int) $duty[0]->period_id);
        $this->assertSame(1_000, (int) $duty[0]->amount_cents);
        $june = BudgetPeriod::query()->whereDate('starts_on', '2027-06-01')->firstOrFail();
        $this->assertSame($june->id, (int) $duty[1]->period_id);
        $this->assertSame(3_206, (int) $duty[1]->amount_cents);
        $this->assertSame(0, PlanLine::query()->where('title', 'Госпошлина')->whereIn('period_id', BudgetPeriod::query()->whereDate('starts_on', '2027-05-01')->orWhereDate('starts_on', '2027-07-01')->pluck('id'))->count());
        $this->assertSame(3_209, (int) $paid->fresh()->amount_cents);
        $this->assertSame($insurance->id, (int) $paid->fresh()->category_id);
        $this->assertSame(1, $paid->transactions()->count());
        $this->assertSame(20_000, (int) $rentLine->fresh()->amount_cents);

        $beforeCreditLines = PlanLine::query()->where('category_id', $credit->id)->sum('amount_cents');
        $card
            ->call('openCredit')
            ->assertSet('showNew', true)
            ->assertSet('newKind', 'bank')
            ->assertSee('Лизинг')
            ->assertSee('Новый договор');
        $this->assertSame(1, Debt::query()->count());

        $card
            ->set('newCounterparty', 'Тестбанк')
            ->set('newAmount', '100,00')
            ->set('newMonths', '2')
            ->set('newDate', '2026-11-20')
            ->set('newInterest', 'none')
            ->call('saveNew')
            ->assertHasNoErrors();

        $debt = Debt::query()->where('counterparty', 'Тестбанк')->firstOrFail();
        $this->assertTrue($object->fresh()->debts()->whereKey($debt->id)->exists());
        $this->assertSame(2, $debt->installments()->count());
        $this->assertSame(0, $debt->installments()->whereNotNull('paid_transaction_id')->count());
        $moved = PlanLine::query()->whereIn('id', $debt->installments()->pluck('plan_line_id'))->get();
        $this->assertCount(2, $moved);
        foreach ($moved as $line) {
            $this->assertSame($object->category->id, (int) $line->category->parent_id);
            $this->assertNotSame($credit->id, (int) $line->category_id);
        }
        $this->assertSame((int) $beforeCreditLines, (int) PlanLine::query()->where('category_id', $credit->id)->sum('amount_cents'));
        $this->assertSame($credit->id, (int) $creditea->fresh()->category_id);
        $this->assertSame(5_312, (int) $creditea->fresh()->amount_cents);
        $this->assertSame($credit->id, (int) $oldDebt->fresh()->category_id);

        $card
            ->set('placeMode', 'root')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($object->category->fresh()->parent_id);
        $this->assertSame(1, Category::query()->where('name', 'Дом')->count());
        $this->assertSame(1, Category::query()->where('name', 'Квартплата Прушу')->count());
        $this->assertSame(20_000, (int) $rentLine->fresh()->amount_cents);
        $this->assertSame($home->id, (int) $rent->fresh()->parent_id);
        $this->assertSame(3_209, (int) $paid->fresh()->amount_cents);

        [$other] = $this->family('Чужая');
        $this->actingAs($other)->get(route('objects.edit', $object))->assertNotFound();
    }

    public function test_two_plan_lines_of_one_category_render_as_one_checkbox(): void
    {
        $this->app->setLocale('ru');
        [$user, $household] = $this->family('Наша');
        $dwelling = $this->category($household, 'Дом');
        $rent = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $dwelling->id,
            'type' => CashflowType::Expense,
            'name' => 'Квартплата Прушу',
            'sort' => 2,
        ]);
        $november = $this->period($household);
        $december = BudgetPeriod::query()->create([
            'household_id' => $household->id,
            'starts_on' => '2026-12-01',
            'ends_on' => '2026-12-31',
            'opening_balance_cents' => 0,
            'status' => 'open',
        ]);
        PlanLine::query()->create([
            'period_id' => $november->id,
            'category_id' => $rent->id,
            'title' => 'Квартплата Прушу',
            'amount_cents' => 19_529,
            'expected_on' => '2026-11-18',
        ]);
        PlanLine::query()->create([
            'period_id' => $december->id,
            'category_id' => $rent->id,
            'title' => 'Квартплата Прушу',
            'amount_cents' => 20_000,
            'expected_on' => '2026-12-05',
        ]);

        $html = Livewire::actingAs($user)->test(PropertyObjectForm::class)->html();

        $this->assertSame(1, substr_count($html, 'data-plan-category="'.$rent->id.'"'));
        $this->assertSame(1, substr_count($html, 'wire:model="categoryIds" value="'.$rent->id.'"'));
    }

    public function test_placing_or_creating_on_an_object_moves_a_category_instead_of_copying_it(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'Europe/Riga'));
        $this->app->setLocale('ru');
        [$user, $household] = $this->family('Наша');
        $dwelling = $this->category($household, 'Дом');
        $utilities = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $dwelling->id,
            'type' => CashflowType::Expense,
            'name' => 'Коммунальные услуги',
            'sort' => 2,
        ]);
        $rentCopy = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $utilities->id,
            'type' => CashflowType::Expense,
            'name' => 'Квартплата Прушу',
            'sort' => 3,
        ]);
        $tax = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $dwelling->id,
            'type' => CashflowType::Expense,
            'name' => 'Налог Прушу',
            'sort' => 4,
        ]);
        $birds = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $dwelling->id,
            'type' => CashflowType::Expense,
            'name' => 'Корм для птиц',
            'sort' => 5,
        ]);
        $prusu = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $dwelling->id,
            'type' => CashflowType::Expense,
            'name' => 'Прушу',
            'sort' => 6,
        ]);
        $rent = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $prusu->id,
            'type' => CashflowType::Expense,
            'name' => 'Квартплата Прушу',
            'sort' => 7,
        ]);
        $schedule = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $prusu->id,
            'type' => CashflowType::Expense,
            'name' => 'Luminor Bank',
            'sort' => 8,
        ]);
        $creditLeaf = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => null,
            'type' => CashflowType::Expense,
            'name' => 'Luminor Bank',
            'sort' => 9,
        ]);
        $credit = $this->category($household, 'Кредит');
        $creditLeaf->update(['parent_id' => $credit->id]);
        $november = $this->period($household);
        $december = BudgetPeriod::query()->create([
            'household_id' => $household->id,
            'starts_on' => '2026-12-01',
            'ends_on' => '2026-12-31',
            'opening_balance_cents' => 0,
            'status' => 'open',
        ]);
        $novemberRent = PlanLine::query()->create([
            'period_id' => $november->id,
            'category_id' => $rentCopy->id,
            'title' => 'Квартплата Прушу',
            'amount_cents' => 19_529,
            'expected_on' => '2026-11-18',
        ]);
        $decemberRent = PlanLine::query()->create([
            'period_id' => $december->id,
            'category_id' => $rent->id,
            'title' => 'Квартплата Прушу',
            'amount_cents' => 20_000,
            'expected_on' => '2026-12-05',
        ]);
        $taxLine = PlanLine::query()->create([
            'period_id' => $november->id,
            'category_id' => $tax->id,
            'title' => 'Налог Прушу',
            'amount_cents' => 1_446,
            'expected_on' => '2026-11-11',
        ]);
        $birdLine = PlanLine::query()->create([
            'period_id' => $november->id,
            'category_id' => $birds->id,
            'title' => 'Корм для птиц',
            'amount_cents' => 2_000,
            'expected_on' => '2026-11-03',
        ]);
        $mortgage = PlanLine::query()->create([
            'period_id' => $november->id,
            'category_id' => $credit->id,
            'title' => 'Luminor Bank',
            'amount_cents' => 27_843,
            'expected_on' => '2026-11-10',
        ]);
        $scheduleLine = PlanLine::query()->create([
            'period_id' => $december->id,
            'category_id' => $schedule->id,
            'title' => 'Luminor Bank',
            'amount_cents' => 28_463,
            'expected_on' => '2026-12-10',
        ]);
        $paid = Transaction::query()->create([
            'household_id' => $household->id,
            'period_id' => $november->id,
            'category_id' => $rentCopy->id,
            'plan_line_id' => $novemberRent->id,
            'type' => CashflowType::Expense,
            'amount_cents' => 19_529,
            'occurred_on' => '2026-11-18',
            'created_by' => $user->id,
        ]);
        $debt = Debt::query()->create([
            'household_id' => $household->id,
            'category_id' => $credit->id,
            'direction' => DebtDirection::Borrow,
            'kind' => DebtKind::Bank,
            'counterparty' => 'Luminor Bank',
        ]);
        $installment = DebtInstallment::query()->create([
            'debt_id' => $debt->id,
            'sequence' => 1,
            'due_on' => '2026-11-10',
            'principal_before_cents' => 1_000_000,
            'principal_cents' => 27_843,
            'interest_cents' => 0,
            'other_cents' => 0,
            'fee_cents' => 0,
            'total_cents' => 27_843,
            'plan_line_id' => $mortgage->id,
            'paid_transaction_id' => $paid->id,
        ]);
        DebtInstallment::query()->create([
            'debt_id' => $debt->id,
            'sequence' => 2,
            'due_on' => '2026-12-10',
            'principal_before_cents' => 900_000,
            'principal_cents' => 28_463,
            'interest_cents' => 0,
            'other_cents' => 0,
            'fee_cents' => 0,
            'total_cents' => 28_463,
            'plan_line_id' => $scheduleLine->id,
        ]);
        $object = PropertyObject::query()->create([
            'household_id' => $household->id,
            'category_id' => $prusu->id,
            'name' => 'Прушу',
            'kind' => 'apartment',
            'label' => 'Прушу 1',
        ]);
        $object->debts()->sync([$debt->id]);
        $object->planLines()->sync([$decemberRent->id, $scheduleLine->id]);
        $beforeLines = PlanLine::query()->count();
        $beforeCents = (int) PlanLine::query()->sum('amount_cents');

        Livewire::actingAs($user)
            ->test(PropertyObjectForm::class, ['propertyObject' => $object])
            ->set('placeMode', 'under')
            ->set('parentCategoryId', $dwelling->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Category::query()->where('name', 'Квартплата Прушу')->count());
        $this->assertSame($prusu->id, (int) $rent->fresh()->parent_id);
        $this->assertNull(Category::query()->find($rentCopy->id));
        $this->assertSame($rent->id, (int) $novemberRent->fresh()->category_id);
        $this->assertSame($rent->id, (int) $decemberRent->fresh()->category_id);
        $this->assertSame(19_529, (int) $novemberRent->fresh()->amount_cents);
        $this->assertSame('2026-11-18', $novemberRent->fresh()->expected_on->toDateString());
        $this->assertSame(20_000, (int) $decemberRent->fresh()->amount_cents);
        $this->assertSame('2026-12-05', $decemberRent->fresh()->expected_on->toDateString());
        $this->assertSame(19_529, (int) $paid->fresh()->amount_cents);
        $this->assertSame('2026-11-18', $paid->fresh()->occurred_on->toDateString());
        $this->assertSame($novemberRent->id, (int) $paid->fresh()->plan_line_id);
        $this->assertSame($rent->id, (int) $paid->fresh()->category_id);
        $this->assertSame($beforeLines, PlanLine::query()->count());
        $this->assertSame($beforeCents, (int) PlanLine::query()->sum('amount_cents'));
        $this->assertSame($credit->id, (int) $mortgage->fresh()->category_id);
        $this->assertSame(27_843, (int) $mortgage->fresh()->amount_cents);
        $this->assertSame($credit->id, (int) $debt->fresh()->category_id);
        $this->assertSame($paid->id, (int) $installment->fresh()->paid_transaction_id);
        $this->assertSame(1, Category::query()->where('name', 'Luminor Bank')->count());
        $this->assertSame($prusu->id, (int) $schedule->fresh()->parent_id);
        $this->assertNull(Category::query()->find($creditLeaf->id));
        $this->assertSame($dwelling->id, (int) $birds->fresh()->parent_id);
        $this->assertSame(2_000, (int) $birdLine->fresh()->amount_cents);

        $card = Livewire::actingAs($user)
            ->test(PropertyObjectForm::class, ['propertyObject' => $object])
            ->set('expenseName', 'Налог Прушу')
            ->set('expenseAmount', '14,46')
            ->set('expenseDate', '2026-11-11')
            ->call('saveExpense')
            ->assertHasNoErrors();

        $this->assertSame(1, Category::query()->where('name', 'Налог Прушу')->count());
        $this->assertSame($prusu->id, (int) $tax->fresh()->parent_id);
        $this->assertSame(1_446, (int) $taxLine->fresh()->amount_cents);
        $this->assertSame('2026-11-11', $taxLine->fresh()->expected_on->toDateString());
        $this->assertSame($tax->id, (int) $taxLine->fresh()->category_id);
        $this->assertSame($dwelling->id, (int) $birds->fresh()->parent_id);

        $card
            ->set('placeMode', 'root')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Category::query()->where('name', 'Прушу')->count());
        $this->assertNull($prusu->fresh()->parent_id);
        $this->assertSame($prusu->id, (int) $rent->fresh()->parent_id);
        $this->assertSame($prusu->id, (int) $tax->fresh()->parent_id);
        $this->assertSame(0, Category::query()->where('parent_id', $dwelling->id)->where('name', 'Прушу')->count());
        $this->assertSame(19_529, (int) $novemberRent->fresh()->amount_cents);
        $this->assertSame(27_843, (int) $mortgage->fresh()->amount_cents);
        $this->assertSame($credit->id, (int) $debt->fresh()->category_id);

        $html = Livewire::actingAs($user)
            ->test(PropertyObjectForm::class, ['propertyObject' => $object])
            ->html();

        $this->assertSame(1, substr_count($html, 'data-plan-category="'.$rent->id.'"'));
        $this->assertSame(1, substr_count($html, 'wire:model="categoryIds" value="'.$rent->id.'"'));
        $this->assertStringNotContainsString('data-plan-category="'.$schedule->id.'"', $html);
    }

    public function test_card_saves_with_name_kind_and_label_when_nothing_exists_to_attach(): void
    {
        [$user] = $this->family('Пустая');

        Livewire::actingAs($user)
            ->test(PropertyObjectForm::class)
            ->assertSee('Долгов пока нет. Карточку можно сохранить без долга.')
            ->assertSee('Строк плана пока нет. Карточку можно сохранить без строк.')
            ->assertSeeHtml('grid-cols-1')
            ->set('name', 'Гараж')
            ->set('kind', 'car')
            ->set('label', 'AA-0001')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $object = PropertyObject::query()->firstOrFail();

        $this->assertSame('Гараж', $object->name);
        $this->assertSame('car', $object->kind->value);
        $this->assertSame('AA-0001', $object->label);
        $this->assertSame(0, $object->debts()->count());
        $this->assertSame(0, $object->planLines()->count());
        $this->assertSame('Гараж', $object->category->name);
        $this->assertNull($object->category->parent_id);
        $this->assertSame(1, Category::query()->count());
        $this->assertSame(0, PlanLine::query()->count());

        $this->actingAs($user)
            ->get(route('objects.index'))
            ->assertOk()
            ->assertSee('Гараж')
            ->assertSee('Объекты')
            ->assertSee(route('objects.create'), false);

        Livewire::actingAs($user)
            ->test(PropertyObjects::class)
            ->assertSee('Гараж')
            ->assertSee('Движимое имущество')
            ->assertDontSee('Машина');
    }

    public function test_renaming_the_object_renames_its_category_and_leaves_the_child(): void
    {
        [$user, $household] = $this->family('Наша');
        $home = $this->category($household, 'Дом');
        $folder = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $home->id,
            'type' => CashflowType::Expense,
            'name' => 'Прушу',
            'sort' => 2,
        ]);
        $rent = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $folder->id,
            'type' => CashflowType::Expense,
            'name' => 'Квартплата Прушу',
            'sort' => 3,
        ]);
        $period = $this->period($household);
        $line = PlanLine::query()->create([
            'period_id' => $period->id,
            'category_id' => $folder->id,
            'title' => 'Прушу',
            'amount_cents' => 100,
            'expected_on' => '2026-11-02',
        ]);
        $childLine = PlanLine::query()->create([
            'period_id' => $period->id,
            'category_id' => $rent->id,
            'title' => 'Квартплата Прушу',
            'amount_cents' => 19_529,
            'expected_on' => '2026-11-18',
        ]);
        $credit = $this->category($household, 'Кредит');
        $mortgage = PlanLine::query()->create([
            'period_id' => $period->id,
            'category_id' => $credit->id,
            'title' => 'Luminor Bank',
            'amount_cents' => 27_843,
            'expected_on' => '2026-11-10',
        ]);
        $debt = Debt::query()->create([
            'household_id' => $household->id,
            'category_id' => $credit->id,
            'direction' => DebtDirection::Borrow,
            'kind' => DebtKind::Bank,
            'counterparty' => 'Luminor Bank',
        ]);
        $object = PropertyObject::query()->create([
            'household_id' => $household->id,
            'category_id' => $folder->id,
            'name' => 'Прушу',
            'kind' => 'apartment',
            'label' => 'Прушу 1',
        ]);
        $object->debts()->sync([$debt->id]);
        $before = Category::query()->count();

        Livewire::actingAs($user)
            ->test(PropertyObjectForm::class, ['propertyObject' => $object])
            ->set('name', 'Prušu 4b-18')
            ->set('label', 'Prušu 4b-18')
            ->call('save')
            ->assertHasNoErrors();

        $object->refresh();
        $this->assertSame('Prušu 4b-18', $object->name);
        $this->assertSame('Prušu 4b-18', $object->label);
        $this->assertSame($folder->id, (int) $object->category_id);
        $this->assertSame('Prušu 4b-18', $folder->fresh()->name);
        $this->assertSame($home->id, (int) $folder->fresh()->parent_id);
        $this->assertSame('Квартплата Прушу', $rent->fresh()->name);
        $this->assertSame($folder->id, (int) $rent->fresh()->parent_id);
        $this->assertSame($before, Category::query()->count());
        $this->assertSame(1, Category::query()->where('name', 'Prušu 4b-18')->count());
        $this->assertSame(0, Category::query()->where('name', 'Прушу')->count());
        $this->assertSame(100, (int) $line->fresh()->amount_cents);
        $this->assertSame('Прушу', $line->fresh()->title);
        $this->assertSame('2026-11-02', $line->fresh()->expected_on->toDateString());
        $this->assertSame($folder->id, (int) $line->fresh()->category_id);
        $this->assertSame(19_529, (int) $childLine->fresh()->amount_cents);
        $this->assertSame('2026-11-18', $childLine->fresh()->expected_on->toDateString());
        $this->assertSame($rent->id, (int) $childLine->fresh()->category_id);
        $this->assertSame(27_843, (int) $mortgage->fresh()->amount_cents);
        $this->assertSame($credit->id, (int) $mortgage->fresh()->category_id);
        $this->assertSame($credit->id, (int) $debt->fresh()->category_id);
    }

    public function test_renaming_onto_a_folder_with_children_keeps_the_object_category(): void
    {
        [$user, $household] = $this->family('Наша');
        $home = $this->category($household, 'Дом');
        Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $home->id,
            'type' => CashflowType::Expense,
            'name' => 'Газ',
            'sort' => 2,
        ]);
        $folder = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $home->id,
            'type' => CashflowType::Expense,
            'name' => 'Прушу',
            'sort' => 3,
        ]);
        $object = PropertyObject::query()->create([
            'household_id' => $household->id,
            'category_id' => $folder->id,
            'name' => 'Прушу',
            'kind' => 'apartment',
            'label' => 'Прушу 1',
        ]);
        $before = Category::query()->count();

        Livewire::actingAs($user)
            ->test(PropertyObjectForm::class, ['propertyObject' => $object])
            ->set('name', 'Дом')
            ->call('save')
            ->assertHasErrors(['name'])
            ->assertSee('Такая категория уже есть.');

        $this->assertSame('Прушу', $object->fresh()->name);
        $this->assertSame($folder->id, (int) $object->fresh()->category_id);
        $this->assertSame('Прушу', $folder->fresh()->name);
        $this->assertSame($before, Category::query()->count());
        $this->assertSame(1, Category::query()->where('name', 'Дом')->count());
    }

    /**
     * @return array{0: User, 1: Household}
     */
    private function family(string $name): array
    {
        $household = Household::query()->create([
            'name' => $name,
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);
        $user = User::factory()->create();
        $household->users()->attach($user->id, ['role' => 'owner']);

        return [$user, $household];
    }

    private function category(Household $household, string $name): Category
    {
        return Category::query()->create([
            'household_id' => $household->id,
            'type' => CashflowType::Expense,
            'name' => $name,
            'sort' => 1,
        ]);
    }

    private function period(Household $household): BudgetPeriod
    {
        return BudgetPeriod::query()->create([
            'household_id' => $household->id,
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-11-30',
            'opening_balance_cents' => 0,
            'status' => 'open',
        ]);
    }
}
