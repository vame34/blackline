<?php

namespace App\Filament\Resources\SignalResource\RelationManagers;

use App\Enums\SignalUpdateType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SignalUpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'updates';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.resources.signal_update.plural');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('type')
                    ->label(__('admin.resources.signal_update.fields.type'))
                    ->required()
                    ->options(self::typeOptions()),
                TextInput::make('title')
                    ->label(__('admin.resources.signal_update.fields.title'))
                    ->maxLength(255),
                Textarea::make('body')
                    ->label(__('admin.resources.signal_update.fields.body'))
                    ->columnSpanFull(),
                DateTimePicker::make('published_at')
                    ->label(__('admin.resources.signal_update.fields.published_at')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label(__('admin.resources.signal_update.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::typeOptions()[$state] ?? (string) $state)
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('admin.resources.signal_update.fields.title'))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('published_at')
                    ->label(__('admin.resources.signal_update.fields.published_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                \Filament\Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                \Filament\Tables\Actions\EditAction::make(),
                \Filament\Tables\Actions\DeleteAction::make(),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['published_at'] = $data['published_at'] ?? now();

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private static function typeOptions(): array
    {
        return [
            SignalUpdateType::Published->value => __('admin.resources.signal_update.options.type.published'),
            SignalUpdateType::Entry->value => __('admin.resources.signal_update.options.type.entry'),
            SignalUpdateType::LevelUpdate->value => __('admin.resources.signal_update.options.type.level_update'),
            SignalUpdateType::Canceled->value => __('admin.resources.signal_update.options.type.canceled'),
            SignalUpdateType::Closed->value => __('admin.resources.signal_update.options.type.closed'),
            SignalUpdateType::Note->value => __('admin.resources.signal_update.options.type.note'),
        ];
    }
}
