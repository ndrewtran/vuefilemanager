<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetupWebsocketEnvironment extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reverb:configure-production';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up Reverb production environment';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $origins = $this->ask('Type the host of your VueFileManager front-end application to allow connection with your websocket server. If you want to accept multiple hosts, separate them with comma(,)');

        setEnvironmentValue([
            'REVERB_ALLOWED_ORIGINS' => $origins,
            'APP_ENV'                => 'production',
            'APP_DEBUG'              => 'false',
        ]);

        $this->info('Your host/s was stored successfully.');

        // Generate new app key
        $this->call('key:generate', [
            '--force' => true,
        ]);

        // Clear cache
        $this->call('config:clear');

        $this->info('Everything is done.');
    }
}
