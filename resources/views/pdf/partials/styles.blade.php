{{--
    Styles communs aux documents PDF (exports, listes de controle, rapports). CSS en ligne, sans
    classes Tailwind : le moteur PDF ne passe pas par Vite. DejaVu Sans est livree avec le moteur
    et couvre les accents et le separateur de milliers insecable des montants.
--}}
<style>
    @page { margin: 28px 32px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1c1917; }
    h1 { font-size: 16px; margin: 0 0 2px; color: {{ $letterhead['primaryColor'] }}; }
    h2 { font-size: 12px; margin: 18px 0 6px; }
    .muted { color: #6b6560; }
    .letterhead { border-bottom: 2px solid {{ $letterhead['primaryColor'] }}; padding-bottom: 8px; margin-bottom: 12px; }
    .letterhead .name { font-size: 13px; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; background: #f1efec; padding: 5px 6px; font-size: 9px; }
    td { padding: 5px 6px; border-bottom: 1px solid #e4e0db; vertical-align: top; }
    .right { text-align: right; }
    .box { display: inline-block; width: 11px; height: 11px; border: 1px solid #1c1917; }
    .table-block { page-break-inside: avoid; margin-bottom: 14px; }
    .cards td { border: 1px solid #e4e0db; padding: 8px; width: 25%; }
    .cards .value { font-size: 15px; font-weight: bold; }
    .signature { margin-top: 28px; page-break-inside: avoid; }
    .signature img { max-height: 70px; max-width: 160px; }
    .footer { margin-top: 18px; font-size: 8px; color: #6b6560; }
</style>
