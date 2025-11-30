<?php

namespace App\Console\Commands;

use App\Models\InviteCode;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateInviteCode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invite:generate {count=1 : How many codes to generate} {--length=12 : Length of each code}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate one-time invite codes for user registration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = (int) $this->argument('count');
        $length = (int) $this->option('length');

        if ($count < 1 || $length < 6) {
            $this->error('Count must be at least 1 and length at least 6.');
            return self::FAILURE;
        }

        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $code = $this->generateUniqueCode($length);
            InviteCode::create([
                'code' => $code,
            ]);
            $codes[] = $code;
        }

        $this->info('Invite codes generated:');
        foreach ($codes as $code) {
            $this->line($code);
        }

        return self::SUCCESS;
    }

    private function generateUniqueCode(int $length): string
    {
        do {
            $code = Str::upper(Str::random($length));
        } while (InviteCode::where('code', $code)->exists());

        return $code;
    }
}
