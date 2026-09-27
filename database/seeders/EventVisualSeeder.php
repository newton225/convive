<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Un visuel d'exemple pour chaque evenement de l'organisation de demonstration qui n'en a pas : le
 * lien public, la vitrine et le formulaire d'evenement montrent sinon un emplacement vide.
 *
 * Images generees ici avec GD plutot que livrees dans le depot : aucune photo a licencier, aucun
 * fichier binaire a versionner. Un fond degrade et des halos de lumiere, dans une palette tiree du
 * nom de l'evenement pour que chaque visuel soit different et stable d'un semis a l'autre.
 */
class EventVisualSeeder extends Seeder
{
    private const Width = 1600;

    private const Height = 900;

    /**
     * Couples de couleurs de fond (haut, bas), en RGB : soirees, diners, matinees.
     *
     * @var array<int, array{0: array{int, int, int}, 1: array{int, int, int}}>
     */
    private const Palettes = [
        [[46, 16, 64], [140, 40, 72]],
        [[12, 34, 64], [24, 110, 128]],
        [[70, 22, 18], [196, 120, 40]],
        [[18, 48, 36], [120, 150, 70]],
        [[30, 24, 70], [200, 90, 120]],
    ];

    public function run(): void
    {
        $tenant = Tenant::where('name', TenantSeeder::TenantName)->first();

        if (! $tenant || ! function_exists('imagecreatetruecolor')) {
            return;
        }

        $tenant->run(function () {
            Event::query()->each(function (Event $event) {
                if ($event->getFirstMedia(Event::VisualCollection) !== null) {
                    return;
                }

                $event->addMedia($this->render($event->name))
                    ->usingFileName('demo-'.$event->id.'.jpg')
                    ->usingName(Event::VisualCollection)
                    ->toMediaCollection(Event::VisualCollection);
            });
        });
    }

    /**
     * Draw the visual into a temporary JPEG and return its path (medialibrary moves it).
     */
    private function render(string $name): string
    {
        $seed = crc32($name);
        mt_srand($seed);
        [$top, $bottom] = self::Palettes[$seed % count(self::Palettes)];

        $image = imagecreatetruecolor(self::Width, self::Height);

        for ($y = 0; $y < self::Height; $y++) {
            $ratio = $y / (self::Height - 1);
            $color = imagecolorallocate(
                $image,
                $this->channel($top[0], $bottom[0], $ratio),
                $this->channel($top[1], $bottom[1], $ratio),
                $this->channel($top[2], $bottom[2], $ratio),
            );
            imageline($image, 0, $y, self::Width, $y, (int) $color);
        }

        imagealphablending($image, true);

        // Halos de lumiere : grands disques tres transparents, puis quelques petits plus vifs.
        for ($i = 0; $i < 38; $i++) {
            $large = $i < 14;
            $diameter = $large ? mt_rand(220, 520) : mt_rand(30, 120);
            $alpha = $large ? mt_rand(108, 120) : mt_rand(80, 110);
            $warm = mt_rand(0, 1) === 1;
            $color = imagecolorallocatealpha(
                $image,
                $warm ? 255 : mt_rand(200, 255),
                $warm ? mt_rand(190, 225) : mt_rand(200, 240),
                $warm ? mt_rand(120, 170) : 255,
                $alpha,
            );
            imagefilledellipse($image, mt_rand(0, self::Width), mt_rand(0, self::Height), $diameter, $diameter, (int) $color);
        }

        $path = tempnam(sys_get_temp_dir(), 'convive-visual-').'.jpg';
        imagejpeg($image, $path, 85);
        imagedestroy($image);
        mt_srand();

        return $path;
    }

    /**
     * Interpolate one colour channel of the background gradient.
     *
     * @return int<0, 255>
     */
    private function channel(int $from, int $to, float $ratio): int
    {
        return max(0, min(255, (int) round($from + ($to - $from) * $ratio)));
    }
}
