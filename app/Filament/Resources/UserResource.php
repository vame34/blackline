<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.access');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.resources.users');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.user.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label(__('admin.resources.user.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('admin.resources.user.fields.email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                DateTimePicker::make('email_verified_at')
                    ->label(__('admin.resources.user.fields.email_verified_at')),
                DateTimePicker::make('banned_at')
                    ->label(__('admin.resources.user.fields.banned_at')),
                Select::make('roles')
                    ->label(__('admin.resources.user.fields.roles'))
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
                TextInput::make('password')
                    ->label(__('admin.resources.user.fields.password'))
                    ->password()
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.resources.user.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('admin.resources.user.fields.email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->badge()
                    ->label(__('admin.resources.user.fields.roles'))
                    ->toggleable(),
                IconColumn::make('email_verified_at')
                    ->boolean()
                    ->label(__('admin.resources.user.fields.verified'))
                    ->toggleable(),
                IconColumn::make('banned_at')
                    ->boolean()
                    ->label(__('admin.resources.user.fields.banned'))
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('admin.resources.user.fields.created_at'))
                    ->dateTime()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
            ])
            ->actions([
                \Filament\Tables\Actions\EditAction::make(),
                Action::make('ban')
                    ->label(__('admin.resources.user.actions.ban'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (User $record): bool => $record->banned_at === null)
                    ->requiresConfirmation()
                    ->action(fn (User $record): bool => (bool) $record->forceFill(['banned_at' => now()])->save()),
                Action::make('unban')
                    ->label(__('admin.resources.user.actions.unban'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (User $record): bool => $record->banned_at !== null)
                    ->requiresConfirmation()
                    ->action(fn (User $record): bool => (bool) $record->forceFill(['banned_at' => null])->save()),
            ])
            ->bulkActions([
                \Filament\Tables\Actions\BulkActionGroup::make([
                    BulkAction::make('banSelected')
                        ->label(__('admin.resources.user.actions.ban_selected'))
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records): int => $records->each(fn (User $user) => $user->forceFill(['banned_at' => now()])->save())->count()),
                    BulkAction::make('unbanSelected')
                        ->label(__('admin.resources.user.actions.unban_selected'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records): int => $records->each(fn (User $user) => $user->forceFill(['banned_at' => null])->save())->count()),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
