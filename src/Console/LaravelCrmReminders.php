<?php

namespace VentureDrake\LaravelCrm\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Notifications\TaskReminderNotification;
use VentureDrake\LaravelCrm\Services\SettingService;

class LaravelCrmReminders extends Command
{
    /**
     * @var SettingService
     */
    private $settingService;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravelcrm:reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder notifications';

    /**
     * The Composer instance.
     *
     * @var \Illuminate\Foundation\Composer
     */
    protected $composer;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Composer $composer, SettingService $settingService)
    {
        parent::__construct();
        $this->composer = $composer;
        $this->settingService = $settingService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Sending Laravel CRM Reminders...');

        foreach(Task::whereNull('completed_at')
            ->where('reminder_email', 0)
            ->where('due_at', '>=', Carbon::now()->timezone($this->settingService->get('timezone')->value ?? 'UTC')->subMinutes(5)->format('Y-m-d H:i:\\00'))
            ->where('due_at', '<=', Carbon::now()->timezone($this->settingService->get('timezone')->value ?? 'UTC')->addMinutes(5)->format('Y-m-d H:i:\\00'))
            ->orderBy('due_at', 'asc')
            ->get() as $task) {
            if ($task->user_assigned_id) {
                $user = \App\User::find($task->user_assigned_id);
                $this->info('Sending task #'.$task->id.' reminder to '.$user->name);

                $user->notify(
                    new TaskReminderNotification($task, $user)
                );

                $task->update([
                    'reminder_email' => 1,
                ]);
            }
        }

        $this->info('Laravel CRM reminders sent.');
    }
}
