<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Actions\Payments\RecordCustomerReceipt;
use App\Actions\Payments\RecordSupplierPayment;
use App\Domain\Accounting\PartyLedger;
use App\Enums\PaymentMethod;
use App\Exceptions\DuplicatePaymentException;
use App\Exceptions\PostingException;
use App\Filament\Resources\PaymentResource;
use App\Models\Customer;
use App\Models\Supplier;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('recordReceipt')
                ->label('Record receipt')
                ->icon('heroicon-o-arrow-down-left')
                ->color('success')
                ->modalHeading('Record customer receipt')
                ->modalSubmitActionLabel('Post receipt')
                ->form([
                    Forms\Components\Select::make('customer_id')->label('Customer')
                        ->options(fn (): array => Customer::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()->required()->live(),
                    Forms\Components\Placeholder::make('outstanding')->label('Outstanding receivable')
                        ->content(function (Forms\Get $get): string {
                            $customer = ($id = $get('customer_id')) ? Customer::find($id) : null;

                            return $customer
                                ? config('erp.currency.symbol').number_format((float) (string) PartyLedger::receivable($customer), 2)
                                : '—';
                        }),
                    Forms\Components\DatePicker::make('date')->default(now())->required(),
                    Forms\Components\TextInput::make('amount')->numeric()->minValue(0.01)->required()
                        ->prefix(config('erp.currency.symbol')),
                    Forms\Components\Select::make('method')->options(['cash' => 'Cash', 'bank' => 'Bank'])
                        ->default('cash')->required(),
                    Forms\Components\TextInput::make('reference')->label('Reference / cheque no.')->maxLength(64),
                    Forms\Components\Textarea::make('note')->maxLength(255),
                ])
                ->action(function (array $data): void {
                    try {
                        $customer = Customer::findOrFail($data['customer_id']);
                        app(RecordCustomerReceipt::class)->handle(
                            $customer,
                            (string) $data['amount'],
                            PaymentMethod::from($data['method']),
                            Carbon::parse($data['date'])->toDateString(),
                            (string) Str::uuid(),
                            $data['reference'] ?? null,
                            $data['note'] ?? null,
                        );
                        Notification::make()->title('Receipt posted')->body('Cash/Bank debited, receivable reduced.')->success()->send();
                    } catch (DuplicatePaymentException|PostingException $e) {
                        Notification::make()->title('Cannot post receipt')->body($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('recordPayment')
                ->label('Record payment')
                ->icon('heroicon-o-arrow-up-right')
                ->color('warning')
                ->modalHeading('Record supplier payment')
                ->modalSubmitActionLabel('Post payment')
                ->form([
                    Forms\Components\Select::make('supplier_id')->label('Supplier')
                        ->options(fn (): array => Supplier::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()->required()->live(),
                    Forms\Components\Placeholder::make('outstanding')->label('Outstanding payable')
                        ->content(function (Forms\Get $get): string {
                            $supplier = ($id = $get('supplier_id')) ? Supplier::find($id) : null;

                            return $supplier
                                ? config('erp.currency.symbol').number_format((float) (string) PartyLedger::payable($supplier), 2)
                                : '—';
                        }),
                    Forms\Components\DatePicker::make('date')->default(now())->required(),
                    Forms\Components\TextInput::make('amount')->numeric()->minValue(0.01)->required()
                        ->prefix(config('erp.currency.symbol')),
                    Forms\Components\Select::make('method')->options(['cash' => 'Cash', 'bank' => 'Bank'])
                        ->default('cash')->required(),
                    Forms\Components\TextInput::make('reference')->label('Reference / cheque no.')->maxLength(64),
                    Forms\Components\Textarea::make('note')->maxLength(255),
                ])
                ->action(function (array $data): void {
                    try {
                        $supplier = Supplier::findOrFail($data['supplier_id']);
                        app(RecordSupplierPayment::class)->handle(
                            $supplier,
                            (string) $data['amount'],
                            PaymentMethod::from($data['method']),
                            Carbon::parse($data['date'])->toDateString(),
                            (string) Str::uuid(),
                            $data['reference'] ?? null,
                            $data['note'] ?? null,
                        );
                        Notification::make()->title('Payment posted')->body('Payable reduced, Cash/Bank credited.')->success()->send();
                    } catch (DuplicatePaymentException|PostingException $e) {
                        Notification::make()->title('Cannot post payment')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
