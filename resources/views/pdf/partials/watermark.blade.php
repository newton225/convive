{{--
    Filigrane des exports PDF (SECURITY.md M3) : identite du demandeur et horodatage, repetes sur
    chaque page (`position: fixed` est reproduit sur toutes les pages par le moteur d'impression).
    Une copie imprimee ou transferee dit encore qui l'a extraite, et quand.
--}}
@isset($watermark)
    @php
        $watermarkText = __('common.pdf.watermark', [
            'name' => $watermark['name'],
            'date' => $watermark['at']->locale(app()->getLocale())->translatedFormat('j F Y, H:i'),
        ]);
    @endphp
    <div class="watermark">{{ $watermarkText }}</div>
    <div class="watermark-footer">{{ $watermarkText }}</div>
@endisset
