<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TestDbController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository as PassportClientRepository;

class SetupController extends Controller
{
    public function changeEnv($data = array())
    {
        if (count($data) > 0) {
            $env = file_get_contents(base_path() . '/.env');
            $env = preg_split('/(\r\n|\n|\r)/', $env);

            foreach ((array) $data as $key => $value) {
                foreach ($env as $env_key => $env_value) {
                    $entry = explode("=", $env_value, 2);
                    if ($entry[0] == $key) {
                        if ($value !== null) {
                            $env[$env_key] = $key . "=" . $value;
                        }
                    } else {
                        $env[$env_key] = $env_value;
                    }
                }
            }

            $env = implode("\n", $env);
            file_put_contents(base_path() . '/.env', $env);
            return true;
        }
        return false;
    }

    public function viewStep1()
    {
        $data = array(
            "APP_NAME"  => session('env.APP_NAME') ? str_replace('"', '', session('env.APP_NAME')) : str_replace('"', '', config('app.name')),
            "APP_ENV"   => session('env.APP_ENV') ? session('env.APP_ENV') : config('app.env'),
            "APP_DEBUG" => session('env.APP_DEBUG') ? session('env.APP_DEBUG') : config('app.debug'),
            "APP_KEY"   => session('env.APP_KEY') ? session('env.APP_KEY') : config('app.key'),
        );
        return view('setup.step1', compact('data'));
    }

    public function viewCheck()
    {
        return view('setup.check');
    }

    public function viewStep2()
    {
        if (config("database.default") == 'mysql') {
            $db = config('database.connections.mysql');
        }

        $data = array(
            "DB_CONNECTION" => session('env.DB_CONNECTION') ? session('env.DB_CONNECTION') : config("database.default"),
            "DB_HOST"      => session('env.DB_HOST') ? session('env.DB_HOST') : (isset($db['host']) ? $db['host'] : ''),
            "DB_PORT"      => session('env.DB_PORT') ? session('env.DB_PORT') : (isset($db['port']) ? $db['port'] : ''),
            "DB_DATABASE"  => session('env.DB_DATABASE') ? session('env.DB_DATABASE') : (isset($db['database']) ? $db['database'] : ''),
            "DB_USERNAME"  => session('env.DB_USERNAME') ? session('env.DB_USERNAME') : (isset($db['username']) ? $db['username'] : ''),
            "DB_PASSWORD"  => session('env.DB_PASSWORD') ? str_replace('"', '', session('env.DB_PASSWORD')) : (isset($db['password']) ? str_replace('"', '', $db['password']) : ''),
        );

        return view('setup.step2', ["data" => $data]);
    }

    public function viewStep3()
    {
        $dbtype = session('env.DB_CONNECTION') ?? config("database.default");

        if ($dbtype == 'mysql') {
            $db = config('database.connections.mysql');
        }

        $dbDatabase = session('env.DB_DATABASE');

        $data = array(
            "APP_NAME"     => str_replace('"', '', session('env.APP_NAME')) == str_replace('"', '', config('app.name')) ? 'old' : str_replace('"', '', session('env.APP_NAME')),
            // ✅ fixed: was session('APP_ENV') (missing 'env.')
            "APP_ENV"      => session('env.APP_ENV') == config('app.env') ? 'old' : session('env.APP_ENV'),
            "APP_DEBUG"    => session('env.APP_DEBUG') == config('app.debug') ? 'old' : session('env.APP_DEBUG'),
            "APP_KEY"      => session('env.APP_KEY') == config('app.key') ? 'old' : session('env.APP_KEY'),
            "DB_CONNECTION"=> session('env.DB_CONNECTION') == config("database.default") ? 'old' : session('env.DB_CONNECTION'),
            "DB_HOST"      => session('env.DB_HOST') == (isset($db['host']) ? $db['host'] : '') ? 'old' : session('env.DB_HOST'),
            "DB_PORT"      => session('env.DB_PORT') == (isset($db['port']) ? $db['port'] : '') ? 'old' : session('env.DB_PORT'),
            "DB_DATABASE"  => $dbDatabase == (isset($db['database']) ? $db['database'] : '') ? 'old' : session('env.DB_DATABASE'),
            "DB_USERNAME"  => session('env.DB_USERNAME') == (isset($db['username']) ? $db['username'] : '') ? 'old' : session('env.DB_USERNAME'),
            "DB_PASSWORD"  => str_replace('"', '', session('env.DB_PASSWORD')) == (isset($db['password']) ? str_replace('"', '', $db['password']) : '') ? 'old' : str_replace('"', '', session('env.DB_PASSWORD')),
        );

        $view = view('setup.step3', compact('data'));
        return $view;
    }

    public function lastStep(Request $request)
    {
        ini_set('max_execution_time', 2000); // 10 minutes
		ini_set('memory_limit','512M');

        try {
            // 1) Persist env
            $this->changeEnv([
                'APP_NAME'      => session('env.APP_NAME'),
                'APP_ENV'       => session('env.APP_ENV'),
                'APP_KEY'       => session('env.APP_KEY'),
                'APP_DEBUG'     => session('env.APP_DEBUG'),
                'APP_URL'       => session('env.APP_URL'),
                'LOG_CHANNEL'   => session('env.LOG_CHANNEL'),

                'DB_CONNECTION' => session('env.DB_CONNECTION'),
                'DB_HOST'       => session('env.DB_HOST'),
                'DB_PORT'       => session('env.DB_PORT'),
                'DB_DATABASE'   => session('env.DB_DATABASE'),
                'DB_USERNAME'   => session('env.DB_USERNAME'),
                'DB_PASSWORD'   => session('env.DB_PASSWORD'),
            ]);
			
			// --- in lastStep(), replace the "runtime DB config" block with this ---
			$connection = session('env.DB_CONNECTION') ?: config('database.default', 'mysql');

			// Unquote env values coming from session (you earlier wrapped DB_PASSWORD in quotes)
			$host = $this->unquote(session('env.DB_HOST'));
			$port = $this->unquote(session('env.DB_PORT'));
			$database = $this->unquote(session('env.DB_DATABASE'));
			$username = $this->unquote(session('env.DB_USERNAME'));
			$password = $this->unquote(session('env.DB_PASSWORD'));

			// Force TCP to dodge socket/grant mismatches (optional but helps on many hosts)
			if ($host === 'localhost') {
				$host = '127.0.0.1';
			}

		// Merge into current connection config
		$current = config("database.connections.$connection", []);
		config(['database.default' => $connection]);
		config(["database.connections.$connection" => array_merge($current, array_filter([
			'driver'   => $connection,
			'host'     => $host,
			'port'     => $port,
			'database' => $database,
			'username' => $username,
			'password' => $password,
		], fn($v) => $v !== null && $v !== ''))]);

		// Reconnect using the new credentials
		DB::purge($connection);
		DB::reconnect($connection);

		// (optional) validate early — will throw if creds are wrong
		DB::connection($connection)->getPdo();


           
            // 3) SAFELY reset database (like migrate:fresh) inside HTTP
            $this->freshDatabase($connection);

            // 4) Migrate & seed (no migrate:fresh)
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);

            // 5) Passport keys (safe; replaces passport:install)
            Artisan::call('passport:keys', ['--force' => true]);

            // 6) Create OAuth clients if missing
            if (Schema::hasTable('oauth_clients')) {
                $hasPassword = DB::table('oauth_clients')->where('password_client', 1)->exists();
                $hasPersonal = DB::table('oauth_clients')->where('personal_access_client', 1)->exists();

                /** @var \Laravel\Passport\ClientRepository $clientRepo */
                $clientRepo = app(PassportClientRepository::class);

                if (!$hasPassword) {
                    $clientRepo->createPasswordGrantClient(
                        null,
                        'Password Grant Client',
                        rtrim(config('app.url'), '/') . '/callback'
                    );
                }
                if (!$hasPersonal) {
                    $clientRepo->createPersonalAccessClient(
                        null,
                        'Personal Access Client',
                        rtrim(config('app.url'), '/') . '/callback'
                    );
                }
            }

            // 7) Mark installed
            Storage::disk('public')->put('installed', 'installed');

            return view('setup.finishedSetup');

        } catch (\Throwable $e) {
            \Log::error('Setup failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function freshDatabase(string $connection): void
    {
        $driver = config("database.connections.$connection.driver", $connection);

        if (in_array($driver, ['mysql', 'mariadb'])) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            // Drop views first
            $views = DB::select(
                "SELECT TABLE_NAME AS name
                 FROM information_schema.views
                 WHERE TABLE_SCHEMA = ?",
                [DB::getDatabaseName()]
            );
            foreach ($views as $view) {
                DB::unprepared('DROP VIEW IF EXISTS `' . $view->name . '`');
            }

            // Drop tables
            $tables = DB::select(
                "SELECT TABLE_NAME AS name
                 FROM information_schema.tables
                 WHERE TABLE_SCHEMA = ? AND TABLE_TYPE='BASE TABLE'",
                [DB::getDatabaseName()]
            );
            foreach ($tables as $table) {
                DB::statement('DROP TABLE IF EXISTS `' . $table->name . '`');
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');

        } elseif ($driver === 'pgsql') {
            DB::statement('DROP SCHEMA public CASCADE');
            DB::statement('CREATE SCHEMA public');
            $username = DB::getConfig('username');
            if ($username) {
                DB::statement('GRANT ALL ON SCHEMA public TO ' . $username);
            }
            DB::statement('GRANT ALL ON SCHEMA public TO public');

        } elseif ($driver === 'sqlite') {
            $path = config("database.connections.$connection.database");
            if ($path && $path !== ':memory:') {
                if (file_exists($path)) {
                    @unlink($path);
                }
                @touch($path);
            }
        } else {
            // Fallback: try rollback massively (avoids migrate:fresh)
            try {
                while (true) {
                    $code = Artisan::call('migrate:rollback', ['--force' => true, '--step' => 1000]);
                    if ($code !== 0) break;
                    if (!Schema::hasTable('migrations') || !DB::table('migrations')->count()) break;
                }
            } catch (\Throwable $e) {
                // ignore; migrate will recreate missing tables
            }
        }
    }

    public function getNewAppKey()
    {
        Artisan::call('key:generate', ['--show' => true]);
        $output = (Artisan::output());
        $output = substr($output, 0, -2);
        return $output;
    }

    public function setupStep1(Request $request)
    {
        $request->session()->put('env.APP_ENV', $request->app_env);
        $request->session()->put('env.APP_DEBUG', $request->app_debug);

        if (strlen($request->app_name) > 0) {
            $request->session()->put('env.APP_NAME', '"' . $request->app_name . '"');
        }

        if (strlen($request->app_key) > 0) {
            $request->session()->put('env.APP_KEY', $request->app_key);
        }

        return $this->viewStep2();
    }

    public function setupStep2(Request $request)
    {
        if (strlen($request->db_password) > 0) {
            $request->session()->put('env.DB_PASSWORD', '"' . $request->db_password . '"');
        }
        $request->session()->put('env.DB_CONNECTION', $request->db_connection);
        $request->session()->put('env.DB_HOST', $request->db_host);
        $request->session()->put('env.DB_PORT', $request->db_port);
        $request->session()->put('env.DB_DATABASE', $request->db_database);
        $request->session()->put('env.DB_USERNAME', $request->db_username);

        if ($request->db_connection == 'sqlite') {
            TestDbController::testSqLite();
        }

        return $this->viewStep3();
    }
	
	private function unquote($v) { return is_string($v) ? trim($v, " \t\n\r\0\x0B\"'") : $v; }


}
