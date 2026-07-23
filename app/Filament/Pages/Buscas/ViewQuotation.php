<?php

namespace App\Filament\Pages\Buscas;

use App\Models\Quotation;
use App\Models\Quotation\Enums\Status;
use App\Models\QuotationItem;
use App\Services\QuotationExport\QuotationExporter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class ViewQuotation extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'cotacoes/{quotation}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.buscas.view-quotation';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'Vendas';

    public Quotation $currentQuotation;

    public function mount(int|string $quotation): void
    {
        $this->currentQuotation = auth()->user()->quotations()->findOrFail($quotation);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->currentQuotation->displayName();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->currentQuotation->items()->getQuery())
            ->recordUrl(fn (QuotationItem $record): ?string => $record->part_id ? ViewPart::getUrl(['record' => $record->part_id]) : null)
            ->columns([
                ImageColumn::make('manufacturer.logo')
                    ->disk('public')
                    ->label('Fabricante')
                    ->imageHeight(20)
                    ->alignCenter(),
                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Qtd.')
                    ->alignCenter(),
                TextColumn::make('descricao')
                    ->label('Descrição')
                    ->placeholder('—')
                    ->limit(40)
                    ->wrap(),
                TextColumn::make('source')
                    ->label('Origem')
                    ->badge(),
                TextColumn::make('note')
                    ->label('Nota')
                    ->placeholder('—')
                    ->limit(40)
                    ->wrap(),
            ])
            ->headerActions([
                ActionGroup::make([
                    Action::make('exportPdf')
                        ->label('PDF')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->action(fn (): StreamedResponse => $this->exportQuotation('pdf')),
                    Action::make('exportCsv')
                        ->label('CSV')
                        ->icon(Heroicon::OutlinedTableCells)
                        ->action(fn (): StreamedResponse => $this->exportQuotation('csv')),
                    Action::make('exportXlsx')
                        ->label('XLSX')
                        ->icon(Heroicon::OutlinedTableCells)
                        ->action(fn (): StreamedResponse => $this->exportQuotation('xlsx')),
                    Action::make('exportJson')
                        ->label('JSON')
                        ->icon(Heroicon::OutlinedCodeBracket)
                        ->action(fn (): StreamedResponse => $this->exportQuotation('json')),
                ])
                    ->label('Exportar')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->button()
                    ->visible(fn (): bool => $this->currentQuotation->items()->exists()),
                Action::make('save')
                    ->label('Salvar cotação')
                    ->modalSubmitActionLabel('Salvar')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (): bool => $this->currentQuotation->status === Status::Open)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome da cotação')
                            ->placeholder($this->currentQuotation->displayName())
                            ->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        $this->currentQuotation->close($data['name'] ?? null);

                        $this->dispatch('quotation-updated');

                        Notification::make()
                            ->title('Cotação salva.')
                            ->success()
                            ->send();
                    }),
                Action::make('reopen')
                    ->label('Reabrir')
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->color('gray')
                    ->visible(fn (): bool => $this->currentQuotation->status === Status::Closed)
                    ->action(function (): void {
                        $this->currentQuotation->reopen();

                        $this->dispatch('quotation-updated');

                        Notification::make()
                            ->title('Cotação reaberta — pode continuar adicionando peças.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('editNote')
                    ->label('Editar')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('gray')
                    ->fillForm(fn (QuotationItem $record): array => [
                        'quantity' => $record->quantity,
                        'note' => $record->note,
                    ])
                    ->schema([
                        TextInput::make('quantity')
                            ->label('Quantidade')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required(),
                        Textarea::make('note')
                            ->label('Nota')
                            ->placeholder('Ex: cliente pede sempre essa, combinar preço...')
                            ->rows(3),
                    ])
                    ->action(function (QuotationItem $record, array $data): void {
                        $record->update([
                            'quantity' => $data['quantity'],
                            'note' => $data['note'],
                        ]);

                        Notification::make()
                            ->title('Item atualizado.')
                            ->success()
                            ->send();
                    }),
                Action::make('remove')
                    ->label('Remover da cotação')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->action(function (QuotationItem $record): void {
                        $record->delete();

                        $this->dispatch('quotation-updated');

                        Notification::make()
                            ->title('Removida da cotação.')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nenhuma peça nessa cotação ainda')
            ->emptyStateDescription('Adicione peças a partir da Base de dados, API, Iframes ou Favoritas.')
            ->emptyStateIcon(Heroicon::OutlinedShoppingCart);
    }

    private function exportQuotation(string $format): StreamedResponse
    {
        $exporter = app(QuotationExporter::class);

        [$content, $extension, $mimeType] = match ($format) {
            'pdf' => [$exporter->toPdf($this->currentQuotation), 'pdf', 'application/pdf'],
            'csv' => [$exporter->toCsv($this->currentQuotation), 'csv', 'text/csv'],
            'xlsx' => [$exporter->toXlsx($this->currentQuotation), 'xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'json' => [$exporter->toJson($this->currentQuotation), 'json', 'application/json'],
        };

        $filename = str($this->currentQuotation->displayName())->slug().".{$extension}";

        return response()->streamDownload(
            fn () => print ($content),
            $filename,
            ['Content-Type' => $mimeType],
        );
    }
}
