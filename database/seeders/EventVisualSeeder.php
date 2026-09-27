<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Une affiche d'exemple pour chaque evenement de l'organisation de demonstration qui n'a pas de
 * visuel depose par un membre : le lien public, la vitrine et le formulaire d'evenement montrent
 * sinon un emplacement vide.
 *
 * Images generees ici avec GD plutot que livrees dans le depot : aucune photo a licencier, aucun
 * fichier binaire a versionner. Le theme (couleurs et motif) se deduit du nom de l'evenement, et
 * le nom et la date sont ecrits dans la bande centrale : le bandeau du lien public recadre l'image
 * en hauteur sur telephone, le haut et le bas ne sont jamais surs d'etre vus.
 */
class EventVisualSeeder extends Seeder
{
    private const Width = 1600;

    private const Height = 900;

    // Prefixe des affiches de ce seeder : un visuel depose a la main ne le porte jamais, il n'est
    // donc jamais remplace ; une affiche d'une version precedente (`demo-`) l'est.
    private const FilePrefix = 'demo-poster-';

    private const TitleFont = 'vendor/dompdf/dompdf/lib/fonts/DejaVuSerif-Bold.ttf';

    private const DetailFont = 'vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf';

    /**
     * Theme par mot du nom : couleurs de fond (haut, bas) en RGB, et motif.
     *
     * @var array<string, array{top: array{int, int, int}, bottom: array{int, int, int}, motif: string}>
     */
    private const Themes = [
        'noel' => ['top' => [14, 58, 40], 'bottom' => [150, 26, 38], 'motif' => 'snow'],
        'louange' => ['top' => [22, 16, 70], 'bottom' => [96, 44, 150], 'motif' => 'rays'],
        'dejeuner' => ['top' => [250, 190, 140], 'bottom' => [206, 96, 60], 'motif' => 'sunrise'],
        'gala' => ['top' => [14, 12, 16], 'bottom' => [110, 18, 44], 'motif' => 'sparkles'],
        'convention' => ['top' => [10, 30, 60], 'bottom' => [20, 110, 130], 'motif' => 'grid'],
        'famille' => ['top' => [20, 70, 60], 'bottom' => [120, 160, 80], 'motif' => 'bokeh'],
    ];

    private const DefaultTheme = ['top' => [40, 30, 80], 'bottom' => [180, 80, 110], 'motif' => 'bokeh'];

    public function run(): void
    {
        $tenant = Tenant::where('name', TenantSeeder::TenantName)->first();

        if (! $tenant || ! function_exists('imagettftext')) {
            return;
        }

        // Affiches de l'organisation de demonstration, redigees en francais comme ses evenements :
        // la date y est ecrite en toutes lettres, dans cette langue quelle que soit celle du terminal.
        App::setLocale('fr');

        $tenant->run(function () {
            Event::query()->each(function (Event $event) {
                $current = $event->getFirstMedia(Event::VisualCollection);

                if ($current instanceof Media && ! str_starts_with($current->file_name, 'demo-')) {
                    return;
                }

                if ($current instanceof Media && str_starts_with($current->file_name, self::FilePrefix)) {
                    return;
                }

                $event->clearMediaCollection(Event::VisualCollection);
                $event->addMedia($this->render($event))
                    ->usingFileName(self::FilePrefix.$event->id.'.jpg')
                    ->usingName(Event::VisualCollection)
                    ->toMediaCollection(Event::VisualCollection);
            });
        });
    }

    /**
     * Draw the poster into a temporary JPEG and return its path (medialibrary moves it).
     */
    private function render(Event $event): string
    {
        $theme = $this->theme($event->name);
        mt_srand(crc32($event->name));

        $image = imagecreatetruecolor(self::Width, self::Height);
        $this->gradient($image, $theme['top'], $theme['bottom']);
        imagealphablending($image, true);

        match ($theme['motif']) {
            'snow' => $this->snow($image),
            'rays' => $this->rays($image),
            'sunrise' => $this->sunrise($image),
            'sparkles' => $this->sparkles($image),
            'grid' => $this->grid($image),
            default => $this->bokeh($image),
        };

        $this->caption($image, $event);

        $path = tempnam(sys_get_temp_dir(), 'convive-visual-').'.jpg';
        imagejpeg($image, $path, 88);
        imagedestroy($image);
        mt_srand();

        return $path;
    }

    /**
     * @return array{top: array{int, int, int}, bottom: array{int, int, int}, motif: string}
     */
    private function theme(string $name): array
    {
        $normalised = strtolower(str_replace(['é', 'è', 'ë', 'ê'], 'e', $name));

        foreach (self::Themes as $word => $theme) {
            if (str_contains($normalised, $word)) {
                return $theme;
            }
        }

        return self::DefaultTheme;
    }

    /**
     * @param  array{int, int, int}  $top
     * @param  array{int, int, int}  $bottom
     */
    private function gradient(\GdImage $image, array $top, array $bottom): void
    {
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
    }

    private function snow(\GdImage $image): void
    {
        for ($i = 0; $i < 260; $i++) {
            $size = mt_rand(3, 14);
            $color = imagecolorallocatealpha($image, 255, 255, 255, mt_rand(20, 90));
            imagefilledellipse($image, mt_rand(0, self::Width), mt_rand(0, self::Height), $size, $size, (int) $color);
        }
    }

    private function rays(\GdImage $image): void
    {
        $originX = (int) (self::Width / 2);

        for ($i = 0; $i < 14; $i++) {
            $angle = deg2rad(20 + $i * 10 + mt_rand(-3, 3));
            $spread = deg2rad(mt_rand(2, 4));
            $length = 1400;
            $color = imagecolorallocatealpha($image, 255, 240, 200, mt_rand(100, 116));
            imagefilledpolygon($image, [
                $originX, -40,
                (int) ($originX + cos($angle - $spread) * $length), (int) (-40 + sin($angle - $spread) * $length),
                (int) ($originX + cos($angle + $spread) * $length), (int) (-40 + sin($angle + $spread) * $length),
            ], (int) $color);
        }

        $this->bokeh($image, 14);
    }

    private function sunrise(\GdImage $image): void
    {
        $centerX = (int) (self::Width * 0.72);
        $centerY = (int) (self::Height * 0.95);

        foreach ([900 => 112, 700 => 104, 520 => 92, 360 => 60] as $diameter => $alpha) {
            $color = imagecolorallocatealpha($image, 255, 230, 150, $alpha);
            imagefilledellipse($image, $centerX, $centerY, $diameter, $diameter, (int) $color);
        }
    }

    private function sparkles(\GdImage $image): void
    {
        for ($i = 0; $i < 120; $i++) {
            $x = mt_rand(0, self::Width);
            $y = mt_rand(0, self::Height);
            $size = mt_rand(4, 22);
            $color = imagecolorallocatealpha($image, 230, 190, 90, mt_rand(20, 90));
            imagefilledpolygon($image, [$x, $y - $size, $x + (int) ($size / 3), $y, $x, $y + $size, $x - (int) ($size / 3), $y], (int) $color);
            imagefilledpolygon($image, [$x - $size, $y, $x, $y - (int) ($size / 3), $x + $size, $y, $x, $y + (int) ($size / 3)], (int) $color);
        }
    }

    private function grid(\GdImage $image): void
    {
        $color = imagecolorallocatealpha($image, 255, 255, 255, 110);

        for ($x = 0; $x <= self::Width; $x += 80) {
            imageline($image, $x, 0, $x, self::Height, (int) $color);
        }

        for ($y = 0; $y <= self::Height; $y += 80) {
            imageline($image, 0, $y, self::Width, $y, (int) $color);
        }

        for ($i = 0; $i < 22; $i++) {
            $dot = imagecolorallocatealpha($image, 120, 230, 240, mt_rand(30, 80));
            imagefilledellipse($image, mt_rand(0, 20) * 80, mt_rand(0, 11) * 80, 16, 16, (int) $dot);
        }
    }

    private function bokeh(\GdImage $image, int $count = 36): void
    {
        for ($i = 0; $i < $count; $i++) {
            $large = $i < $count / 3;
            $diameter = $large ? mt_rand(220, 520) : mt_rand(30, 120);
            $color = imagecolorallocatealpha($image, 255, mt_rand(200, 235), mt_rand(150, 210), $large ? mt_rand(108, 120) : mt_rand(80, 110));
            imagefilledellipse($image, mt_rand(0, self::Width), mt_rand(0, self::Height), $diameter, $diameter, (int) $color);
        }
    }

    /**
     * Write the event name and date in the central band, over a translucent strip for contrast.
     */
    private function caption(\GdImage $image, Event $event): void
    {
        $strip = imagecolorallocatealpha($image, 0, 0, 0, 80);
        imagefilledrectangle($image, 0, 290, self::Width, 610, (int) $strip);

        $white = (int) imagecolorallocate($image, 255, 255, 255);
        $soft = (int) imagecolorallocatealpha($image, 255, 255, 255, 25);
        $titleFont = base_path(self::TitleFont);
        $detailFont = base_path(self::DetailFont);

        $lines = $this->wrap($event->name, $titleFont, 84, 1360);
        $lineHeight = 108;
        $top = count($lines) === 1 ? 440 : 400;

        foreach ($lines as $index => $line) {
            $this->centered($image, $line, $titleFont, 84, $top + $index * $lineHeight, $white);
        }

        $date = $event->starts_at?->translatedFormat('j F Y');

        if ($date !== null) {
            $this->centered($image, mb_strtoupper($date), $detailFont, 34, $top + count($lines) * $lineHeight - 20, $soft);
        }
    }

    /**
     * @return array<int, string>
     */
    private function wrap(string $text, string $font, int $size, int $maxWidth): array
    {
        $lines = [];
        $current = '';

        foreach (explode(' ', $text) as $word) {
            $candidate = trim($current.' '.$word);

            if ($current !== '' && $this->textWidth($candidate, $font, $size) > $maxWidth) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        $lines[] = $current;

        return array_slice($lines, 0, 2);
    }

    private function centered(\GdImage $image, string $text, string $font, int $size, int $baseline, int $color): void
    {
        $x = (int) ((self::Width - $this->textWidth($text, $font, $size)) / 2);
        imagettftext($image, $size, 0, $x, $baseline, $color, $font, $text);
    }

    private function textWidth(string $text, string $font, int $size): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box === false ? 0 : abs($box[2] - $box[0]);
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
