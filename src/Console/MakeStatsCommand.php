<?php

namespace Spatie\Stats\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeStatsCommand extends Command
{
    protected $signature = 'stats:make {model : The model name (e.g., User, Post, Tenant)}
                            {--tenant-aware : Make this stat model tenant-aware}';

    protected $description = 'Create a stats table and model for tracking statistics';

    public function handle()
    {
        $modelName = $this->argument('model');
        $isTenantAware = $this->option('tenant-aware');

        $statModelName = $modelName.'Stat';
        $tableName = Str::snake(Str::plural($modelName)).'_stats';
        $foreignKey = Str::snake($modelName).'_id';

        // Generate migration
        $this->generateMigration($modelName, $tableName, $foreignKey, $isTenantAware);

        // Generate model
        $this->generateModel($modelName, $statModelName, $foreignKey, $isTenantAware);

        $this->info("Stats files created successfully for {$modelName}!");
        $this->comment("Migration: database/migrations/*_create_{$tableName}_table.php");
        $this->comment("Model: app/Models/{$statModelName}.php");
        $this->newLine();
        $this->comment("Next steps:");
        $this->comment("1. Run: php artisan migrate");
        $this->comment("2. Use: {$statModelName}::for(\${$this->modelVariable($modelName)})".($isTenantAware ? '->on($tenant)' : '')."->increase('stat_name')");
    }

    protected function generateMigration(string $modelName, string $tableName, string $foreignKey, bool $isTenantAware)
    {
        $stub = $isTenantAware
            ? $this->getTenantAwareMigrationStub()
            : $this->getRegularMigrationStub();

        $content = str_replace(
            ['{{ table }}', '{{ foreign_key }}', '{{ model_table }}'],
            [$tableName, $foreignKey, Str::snake(Str::plural($modelName))],
            $stub
        );

        $timestamp = date('Y_m_d_His');
        $fileName = database_path("migrations/{$timestamp}_create_{$tableName}_table.php");

        File::put($fileName, $content);
    }

    protected function generateModel(string $modelName, string $statModelName, string $foreignKey, bool $isTenantAware)
    {
        $stub = $isTenantAware
            ? $this->getTenantAwareModelStub()
            : $this->getRegularModelStub();

        $relationName = Str::camel($modelName);

        $content = str_replace(
            ['{{ class }}', '{{ model }}', '{{ relation }}', '{{ foreign_key }}'],
            [$statModelName, $modelName, $relationName, $foreignKey],
            $stub
        );

        $fileName = app_path("Models/{$statModelName}.php");

        if (File::exists($fileName)) {
            if (! $this->confirm("Model {$statModelName} already exists. Overwrite?", false)) {
                $this->warn("Skipping model generation.");

                return;
            }
        }

        File::ensureDirectoryExists(app_path('Models'));
        File::put($fileName, $content);
    }

    protected function getTenantAwareMigrationStub(): string
    {
        $stubPath = __DIR__.'/../Stubs/stat_migration_tenant_aware.stub';

        if (File::exists($stubPath)) {
            return File::get($stubPath);
        }

        // Inline stub if file doesn't exist
        return <<<'STUB'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('{{ table }}', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('{{ foreign_key }}')->constrained('{{ model_table }}')->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->bigInteger('value');
            $table->timestamps();

            $table->index(['tenant_id', '{{ foreign_key }}', 'name', 'created_at']);
            $table->index(['{{ foreign_key }}', 'name', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('{{ table }}');
    }
};
STUB;
    }

    protected function getRegularMigrationStub(): string
    {
        $stubPath = __DIR__.'/../Stubs/stat_migration_regular.stub';

        if (File::exists($stubPath)) {
            return File::get($stubPath);
        }

        // Inline stub if file doesn't exist
        return <<<'STUB'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('{{ table }}', function (Blueprint $table) {
            $table->id();
            $table->foreignId('{{ foreign_key }}')->constrained('{{ model_table }}')->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->bigInteger('value');
            $table->timestamps();

            $table->index(['{{ foreign_key }}', 'name', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('{{ table }}');
    }
};
STUB;
    }

    protected function getTenantAwareModelStub(): string
    {
        $stubPath = __DIR__.'/../Stubs/stat_model_tenant_aware.stub';

        if (File::exists($stubPath)) {
            return File::get($stubPath);
        }

        // Inline stub if file doesn't exist
        return <<<'STUB'
<?php

namespace App\Models;

use Spatie\Stats\BaseStatModel;
use Spatie\Stats\Traits\IsTenantAware;

class {{ class }} extends BaseStatModel
{
    use IsTenantAware;

    public function {{ relation }}()
    {
        return $this->belongsTo({{ model }}::class);
    }

    public static function getModelForeignKey(): string
    {
        return '{{ foreign_key }}';
    }
}
STUB;
    }

    protected function getRegularModelStub(): string
    {
        $stubPath = __DIR__.'/../Stubs/stat_model_regular.stub';

        if (File::exists($stubPath)) {
            return File::get($stubPath);
        }

        // Inline stub if file doesn't exist
        return <<<'STUB'
<?php

namespace App\Models;

use Spatie\Stats\BaseStatModel;

class {{ class }} extends BaseStatModel
{
    public function {{ relation }}()
    {
        return $this->belongsTo({{ model }}::class);
    }

    public static function getModelForeignKey(): string
    {
        return '{{ foreign_key }}';
    }
}
STUB;
    }

    protected function modelVariable(string $modelName): string
    {
        return Str::camel($modelName);
    }
}
