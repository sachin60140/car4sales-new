<?php

namespace App\Http\Controllers\Public;

use App\Domain\PublicWebsite\Services\SitemapGenerator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function sitemap(SitemapGenerator $generator): Response
    {
        return response($generator->xml(), 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(SitemapGenerator $generator): Response
    {
        return response($generator->robots(), 200, ['Content-Type' => 'text/plain']);
    }
}
