<?php

namespace App\Console\Commands;

use App\Models\Section;
use App\Models\Show;
use App\Services\Assets\AssetImporter;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Generates obviously-placeholder graphics at the exact dimensions each section
 * expects, so the routing grid can be exercised before any real art exists.
 * Nothing here is meant to go to air.
 */
class GenerateDemoAssets extends Command
{
    protected $signature = 'broadcast:demo-assets
                            {--show= : Show slug to read section sizes from. Defaults to the first show.}';

    protected $description = 'Create placeholder graphics sized to each section, for testing the board';

    public function handle(AssetImporter $importer): int
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->error('The GD extension is required to generate placeholder images.');

            return self::FAILURE;
        }

        $show = $this->option('show')
            ? Show::where('slug', $this->option('show'))->first()
            : Show::with('sections')->first();

        if (! $show) {
            $this->error('No broadcast found. Create one first.');

            return self::FAILURE;
        }

        $show->load('sections');

        $created = 0;

        foreach ($show->sections as $section) {
            if (! $section->hasDimensions()) {
                continue;
            }

            // A few variants per section so the grid has something to choose
            // between rather than a single row.
            foreach (['A', 'B', 'C'] as $variant) {
                $this->store($importer, $section, "{$section->label} Placeholder {$variant}", $variant);
                $created++;
            }
        }

        $this->info($created.' placeholder assets stored.');

        return self::SUCCESS;
    }

    protected function store(AssetImporter $importer, Section $section, string $name, string $variant): void
    {
        $path = tempnam(sys_get_temp_dir(), 'demo').'.png';

        $this->draw($path, $section->width, $section->height, $section->label.' '.$variant, $variant);

        $importer->import(
            new UploadedFile($path, Str::slug($name).'.png', 'image/png', null, true),
            $name,
            ['placeholder', Str::slug($section->key)],
        );

        @unlink($path);
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
}
