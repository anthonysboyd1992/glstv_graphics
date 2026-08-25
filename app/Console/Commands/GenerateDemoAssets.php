<?php

namespace App\Console\Commands;

use App\Models\AssetPack;
use App\Models\AssetRole;
use App\Models\Section;
use App\Models\ShowTemplate;
use App\Services\Assets\AssetImporter;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Generates obviously-placeholder graphics at the exact dimensions each section
 * expects, so the routing grid and packs can be exercised before any real art
 * exists. Nothing here is meant to go to air.
 */
class GenerateDemoAssets extends Command
{
    protected $signature = 'broadcast:demo-assets
                            {--template=dirt-track : Template slug to read section sizes from}
                            {--fill-pack : Also fill the House Defaults pack with the generated art}';

    protected $description = 'Create placeholder graphics sized to each section, for testing the board';

    public function handle(AssetImporter $importer): int
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->error('The GD extension is required to generate placeholder images.');

            return self::FAILURE;
        }

        $template = ShowTemplate::where('slug', $this->option('template'))->first();

        if (! $template) {
            $this->error("No template found with slug [{$this->option('template')}].");

            return self::FAILURE;
        }

        $created = [];

        foreach ($template->sections as $section) {
            if (! $section->hasDimensions()) {
                continue;
            }

            // A few variants per section so the grid has something to choose
            // between rather than a single row.
            foreach (['A', 'B', 'C'] as $variant) {
                $name = "{$section->label} Placeholder {$variant}";

                $created[] = $this->store($importer, $section, $name, $variant);
            }
        }

        $this->info(count($created).' placeholder assets stored.');

        if ($this->option('fill-pack')) {
            $this->fillPack($template, $created);
        }

        return self::SUCCESS;
    }

    protected function store(AssetImporter $importer, Section $section, string $name, string $variant): int
    {
        $path = tempnam(sys_get_temp_dir(), 'demo').'.png';

        $this->draw($path, $section->width, $section->height, $section->label.' '.$variant, $variant);

        $asset = $importer->import(
            new UploadedFile($path, Str::slug($name).'.png', 'image/png', null, true),
            $name,
            ['placeholder', Str::slug($section->key)],
        );

        @unlink($path);

        return $asset->id;
    }

    protected function draw(string $path, int $width, int $height, string $label, string $variant): void
    {
        $image = imagecreatetruecolor($width, $height);

        [$r, $g, $b] = match ($variant) {
            'A' => [190, 40, 40],
            'B' => [40, 90, 170],
            default => [50, 130, 80],
        };

        imagefill($image, 0, 0, imagecolorallocate($image, $r, $g, $b));

        $border = imagecolorallocate($image, 255, 255, 255);
        imagerectangle($image, 2, 2, $width - 3, $height - 3, $border);

        $text = "{$label}  {$width}x{$height}";
        $scale = max(1, (int) min(5, floor(min($width, $height) / 40)));

        imagestring(
            $image,
            $scale,
            max(6, (int) (($width - imagefontwidth($scale) * strlen($text)) / 2)),
            max(4, (int) (($height - imagefontheight($scale)) / 2)),
            $text,
            $border
        );

        imagepng($image, $path);
        imagedestroy($image);
    }

    /**
     * @param  array<int, int>  $assetIds
     */
    protected function fillPack(ShowTemplate $template, array $assetIds): void
    {
        $pack = AssetPack::firstOrCreate(
            ['slug' => 'house-defaults'],
            ['name' => 'House Defaults']
        );

        $roles = AssetRole::where('show_template_id', $template->id)->orderBy('sort_order')->get();

        foreach ($roles as $index => $role) {
            if (! isset($assetIds[$index])) {
                break;
            }

            $pack->items()->updateOrCreate(
                ['role_key' => $role->key],
                ['asset_id' => $assetIds[$index]],
            );
        }

        $this->info("Filled {$pack->name} with placeholder art.");
    }
}
