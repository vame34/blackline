<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SignalCategoryResource\Pages;
use App\Models\SignalCategory;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SignalCategoryResource extends Resource
{
    protected static ?string $model = SignalCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.signals');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.resources.signal_categories');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.signal_category.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.signal_category.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('slug')
                    ->label(__('admin.resources.signal_category.fields.slug'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label(__('admin.resources.signal_category.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('sort_order')
                    ->label(__('admin.resources.signal_category.fields.sort_order'))
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.resources.signal_category.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('admin.resources.signal_category.fields.slug'))
                    ->toggleable(),
                TextColumn::make('sort_order')
                    ->label(__('admin.resources.signal_category.fields.sort_order'))
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('admin.resources.signal_category.fields.updated_at'))
                    ->dateTime()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
            ])
            ->actions([
                \Filament\Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Tables\Actions\BulkActionGroup::make([
                    \Filament\Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSignalCategories::route('/'),
            'create' => Pages\CreateSignalCategory::route('/create'),
            'edit' => Pages\EditSignalCategory::route('/{record}/edit'),
        ];
    }
}
