<?php

use App\Models\Card;
use App\Models\Company;
use App\Models\Employee;
use App\Services\CardService;
use Illuminate\Validation\ValidationException;

function cardServiceEmployee(): Employee
{
    $company = Company::create(['name' => 'Teszt Kft.']);

    return Employee::create(['name' => 'Teszt Dolgozó', 'company_id' => $company->id]);
}

it('assigns a card whose stored uid contains dashes, spaces, or colons', function () {
    $employee = cardServiceEmployee();
    $card = Card::create(['uid' => 'TESZT-WEBHOOK-0001', 'status' => 'available']);

    $result = app(CardService::class)->assignByUid($employee->id, $card->uid);

    expect($result->employee_id)->toBe($employee->id);
    expect($result->status)->toBe('assigned');
});

it('rejects assigning a card already belonging to another employee', function () {
    $employeeA = cardServiceEmployee();
    $employeeB = cardServiceEmployee();
    $card = Card::create(['uid' => 'CARD-A', 'status' => 'assigned', 'employee_id' => $employeeA->id]);

    expect(fn () => app(CardService::class)->assignByUid($employeeB->id, $card->uid))
        ->toThrow(ValidationException::class);
});

it('rejects assigning a card to an employee who already has one', function () {
    $employee = cardServiceEmployee();
    Card::create(['uid' => 'CARD-EXISTING', 'status' => 'assigned', 'employee_id' => $employee->id]);
    $otherCard = Card::create(['uid' => 'CARD-OTHER', 'status' => 'available']);

    expect(fn () => app(CardService::class)->assignByUid($employee->id, $otherCard->uid))
        ->toThrow(ValidationException::class);
});

it('rejects assigning a blocked or lost card', function () {
    $employee = cardServiceEmployee();
    $card = Card::create(['uid' => 'CARD-BLOCKED', 'status' => 'blocked']);

    expect(fn () => app(CardService::class)->assignByUid($employee->id, $card->uid))
        ->toThrow(ValidationException::class);
});

it('unassigns a card back to available', function () {
    $employee = cardServiceEmployee();
    $card = Card::create(['uid' => 'CARD-UNASSIGN', 'status' => 'assigned', 'employee_id' => $employee->id]);

    $result = app(CardService::class)->unassign($card->id);

    expect($result->employee_id)->toBeNull();
    expect($result->status)->toBe('available');
});
