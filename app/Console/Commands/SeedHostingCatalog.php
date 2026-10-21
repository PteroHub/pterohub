<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Seed a conservative draft catalog from remote Egg IDs already synchronized from this panel. */
class SeedHostingCatalog extends Command
{
    protected $signature = 'pterohub:seed-games {--dry-run : Inspect proposed changes without writing}';
    protected $description = 'Seed draft game categories and associate known existing Egg IDs; no prices, plans, sales or server actions';

    public function handle(): int
    {
        // Remote IDs confirmed by the Pterodactyl inventory output from alpha.
        // One game may have multiple eggs; full/demo remain separate products.
        $catalog = [
            ['Call of Duty', 'IW Engine', [27]],
            ['Call of Duty: United Offensive', 'IW Engine', [28]],
            ['Call of Duty 2', 'IW Engine', [22]],
            ['Call of Duty 4: Modern Warfare', 'IW Engine / id Tech 3', [17]],
            ['Call of Duty: World at War', 'IW Engine', [23]],
            ['Soldier of Fortune II', 'id Tech 3 Engine', [16]],
            ['Soldier of Fortune II Demo', 'id Tech 3 Engine', [21]],
            ['Quake III Arena', 'id Tech 3 Engine', [25]],
            ['Quake 4', 'id Tech 4 Engine', [26]],
            ['Unreal Tournament 2004', 'Unreal Engine 2', [29]],
        ];
        $isDry = (bool) $this->option('dry-run');
        $missing = [];
        $conflicts = [];
        $work = function () use ($catalog, $isDry, &$missing, &$conflicts): void {
            foreach ($catalog as [$name, $engine, $remoteIds]) {
                $slug = Str::slug($name);
                $existing = DB::table('games')->where('slug', $slug)->first();
                $matchByName = DB::table('games')->where('name',$name)->first();
                if (!$existing && $matchByName) $existing = $matchByName;
                $this->line(($existing ? 'EXISTS ' : 'CREATE ').$name.' ['.$engine.']');
                $gameId = $existing?->id;
                if (!$existing && !$isDry) {
                    $gameId = DB::table('games')->insertGetId([
                        'name'=>$name, 'slug'=>$slug, 'engine'=>$engine,
                        'description'=>null, 'active'=>false, 'sort_order'=>0,
                        'created_at'=>now(), 'updated_at'=>now(),
                    ]);
                }
                foreach ($remoteIds as $remoteId) {
                    $egg=DB::table('pterodactyl_eggs')->where('remote_id',$remoteId)->first();
                    if (!$egg) {
                        $missing[]=$remoteId;
                        $this->warn("  MISSING remote Egg #{$remoteId} — skipped");
                        continue;
                    }
                    if ($egg->game_id && (!$existing || (int)$egg->game_id !== (int)$existing->id)) {
                        $conflicts[]=$remoteId;
                        $this->warn("  KEEP Egg #{$remoteId}: already assigned to game ID {$egg->game_id}");
                        continue;
                    }
                    $this->line("  ".($egg->game_id ? 'LINKED' : 'ASSIGN')." Egg #{$remoteId}: {$egg->name}");
                    if (!$isDry && !$egg->game_id) {
                        DB::table('pterodactyl_eggs')->where('id',$egg->id)->update([
                            'game_id'=>$gameId, 'updated_at'=>now(),
                        ]);
                    }
                }
            }
        };
        if ($isDry) $work();
        else DB::transaction($work);
        $this->newLine();
        $this->info($isDry ? 'DRY RUN: no data changed.' : 'Draft games seeded. No plans, prices or sale flags changed.');
        if ($missing) $this->warn('Missing Egg IDs: '.implode(', ',array_unique($missing)));
        if ($conflicts) $this->warn('Existing assignments preserved for Egg IDs: '.implode(', ',array_unique($conflicts)));
        return self::SUCCESS;
    }
}
