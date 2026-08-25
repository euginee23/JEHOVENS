<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password as promptPassword;
use function Laravel\Prompts\text;

class MakeAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'resort:make-admin
                            {--name= : The administrator\'s name}
                            {--email= : The administrator\'s email address}
                            {--password= : The password (prompted for when omitted)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an administrator account for the resort admin area';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->option('name') ?: text(
            label: 'Name',
            required: true,
        );

        $email = $this->option('email') ?: text(
            label: 'Email address',
            required: true,
        );

        $password = $this->option('password') ?: promptPassword(
            label: 'Password',
            required: true,
        );

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
                'password' => ['required', 'string', Password::default()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        // Admins are created by hand, so there is nobody to click a verification link.
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->components->info("Administrator [{$user->email}] created.");
        $this->components->bulletList([
            'Sign in at '.route('login'),
        ]);

        return self::SUCCESS;
    }
}
