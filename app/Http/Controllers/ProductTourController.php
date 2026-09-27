<?php

namespace App\Http\Controllers;

use App\Enums\ProductTour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductTourController extends Controller
{
    /**
     * Remember that the member has finished or dismissed the given tour.
     */
    public function complete(Request $request, ProductTour $tour): RedirectResponse
    {
        $request->user()->completeTour($tour);

        return back();
    }
}
