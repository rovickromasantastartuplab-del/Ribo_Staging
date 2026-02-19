<?php

namespace Livechat\Commands;

use App\Models\User;
use Common\Auth\Actions\DeleteUsers;
use Illuminate\Console\Command;

class PruneVisitors extends Command
{
    protected $signature = 'visitors:prune';

    public function handle()
    {
        $days = settings('lc.timeout.pruneVisitor');

        $usedPruned = 0;

        if ($days) {
            User::whereNull('email')
                ->whereNull('password')
                ->where('type', 'user')
                ->where('created_at', '<', now()->subDays($days))
                ->whereDoesntHave('conversations')
                ->whereDoesntHave(
                    'roles',
                    fn($q) => $q->where('guests', '!=', true),
                )
                ->whereDoesntHave(
                    'userSessions',
                    fn($q) => $q->where(
                        'updated_at',
                        '>',
                        now()->subDays($days),
                    ),
                )
                ->chunkById(100, function ($users) use (&$usedPruned) {
                    (new DeleteUsers())->execute(
                        $users->pluck('id')->toArray(),
                    );
                    $usedPruned += $users->count();
                });
        }

        if ($usedPruned > 0) {
            $this->info("Pruned $usedPruned visitors");
        }

        $this->info('No visitors to prune');
    }
}
