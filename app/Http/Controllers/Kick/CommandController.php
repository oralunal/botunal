<?php

namespace App\Http\Controllers\Kick;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kick\CommandStoreRequest;
use App\Http\Requests\Kick\CommandUpdateRequest;
use App\Models\Command;
use App\Services\Kick\BuiltInCommandRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CommandController extends Controller
{
    /**
     * List all commands with their aliases.
     */
    public function index(): Response
    {
        return Inertia::render('kick/Commands', [
            'commands' => Command::query()
                ->with('aliases:id,command_id,alias')
                ->orderBy('name')
                ->get(),
            'handlers' => BuiltInCommandRegistry::handlers(),
        ]);
    }

    public function store(CommandStoreRequest $request): RedirectResponse
    {
        $this->persist(new Command, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Command created.')]);

        return to_route('kick.commands.index');
    }

    public function update(CommandUpdateRequest $request, Command $command): RedirectResponse
    {
        $this->persist($command, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Command updated.')]);

        return to_route('kick.commands.index');
    }

    public function destroy(Command $command): RedirectResponse
    {
        $command->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Command deleted.')]);

        return to_route('kick.commands.index');
    }

    /**
     * Persist a command and sync its aliases in one transaction.
     *
     * @param  array<string, mixed>  $data
     */
    private function persist(Command $command, array $data): void
    {
        $aliases = collect($data['aliases'] ?? [])
            ->map(fn (string $alias): string => strtolower(trim($alias)))
            ->filter()
            ->unique()
            ->values();

        unset($data['aliases']);

        DB::transaction(function () use ($command, $data, $aliases): void {
            $command->fill($data);

            if (! $command->exists) {
                $command->created_by = auth()->id();
            }

            $command->save();

            $command->aliases()->delete();
            $command->aliases()->createMany(
                $aliases->map(fn (string $alias): array => ['alias' => $alias])->all(),
            );
        });
    }
}
