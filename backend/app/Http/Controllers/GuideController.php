<?php

namespace App\Http\Controllers;

use App\Support\GuideContent;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\View\View;

class GuideController extends Controller
{
    public function show(): View
    {
        return view('guide.show', [
            'meta' => GuideContent::meta(),
            'chapters' => GuideContent::chapters(),
        ]);
    }

    public function pdf(): Response
    {
        $html = view('guide.pdf', [
            'meta' => GuideContent::meta(),
            'chapters' => GuideContent::chapters(),
        ])->render();

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('defaultPaperSize', 'a4');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'Guide-BeninFreelance-v'.GuideContent::meta()['version'].'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
