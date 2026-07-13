<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages;
use App\Models\User;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only audit view (requirement 6). Lists the `updated` / `deleted` activity_log
 * entries written by the Lead model — what changed, from → to, when, and by whom.
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Activity Log';

    protected static ?string $modelLabel = 'activity';

    protected static ?int $navigationSort = 2;

    /** Audit rows are never created by hand. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** Eager-load the morphs so the list isn't N+1. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['causer', 'subject']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event')
                    ->label('Action')
                    ->badge()
                    ->color(fn (?string $state): string => static::eventColor($state)),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Lead')
                    ->getStateUsing(fn (Activity $record): string => static::subjectLabel($record)),
                Tables\Columns\TextColumn::make('causer')
                    ->label('Changed by')
                    ->getStateUsing(fn (Activity $record): string => $record->causer?->name ?? 'System'),
                Tables\Columns\TextColumn::make('changes')
                    ->label('Changes')
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(fn (Activity $record): array => array_map(
                        fn (array $r): string => "{$r['field']}: {$r['old']} → {$r['new']}",
                        static::changeSet($record),
                    ))
                    ->limitList(3),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->options([
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),
                Tables\Filters\SelectFilter::make('causer_id')
                    ->label('Changed by')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\TextEntry::make('created_at')->label('When')->dateTime(),
                Infolists\Components\TextEntry::make('event')->label('Action')->badge()
                    ->color(fn (?string $state): string => static::eventColor($state)),
                Infolists\Components\TextEntry::make('subject')->label('Lead')
                    ->state(fn (Activity $record): string => static::subjectLabel($record)),
                Infolists\Components\TextEntry::make('causer')->label('Changed by')
                    ->state(fn (Activity $record): string => $record->causer?->name ?? 'System'),
                Infolists\Components\ViewEntry::make('changes')->label('Changes')
                    ->view('filament.activity-changes')
                    ->state(fn (Activity $record): array => static::changeSet($record)),
            ]);
    }

    /**
     * Structured field-level diff from an activity's logged properties.
     * Handles updates (old → new) and deletes (old values → —).
     *
     * @return array<int, array{field: string, old: string, new: string}>
     */
    public static function changeSet(Activity $activity): array
    {
        $changes = $activity->changes();
        $new = (array) $changes->get('attributes', []);
        $old = (array) $changes->get('old', []);

        $keys = array_values(array_unique([...array_keys($new), ...array_keys($old)]));

        return array_map(fn (string $key): array => [
            'field' => static::fieldLabel($key),
            'old' => static::stringify($old[$key] ?? null),
            'new' => static::stringify($new[$key] ?? null),
        ], $keys);
    }

    private static function fieldLabel(string $key): string
    {
        return match ($key) {
            'monthly_bill_rm' => 'Monthly bill',
            'customer_name' => 'Customer name',
            'property_type' => 'Property type',
            'roof_type' => 'Roof type',
            default => ucfirst($key),
        };
    }

    private static function eventColor(?string $event): string
    {
        return match ($event) {
            'updated' => 'warning',
            'deleted' => 'danger',
            default => 'gray',
        };
    }

    private static function subjectLabel(Activity $activity): string
    {
        return $activity->subject?->customer_name ?? 'Lead #'.$activity->subject_id;
    }

    private static function stringify(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
            'view' => Pages\ViewActivity::route('/{record}'),
        ];
    }
}
