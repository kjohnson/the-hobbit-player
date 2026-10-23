<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Books the tests play instead of the real ones, which may not have any
     * chapters yet. A released book, a short second one and one coming soon.
     *
     * @var array<string, array<string, mixed>>
     */
    protected const BOOKS = [
        'first' => [
            'title' => 'First Book',
            'label' => 'Book 1',
            'narrator' => 'First Reader',
            'cover' => 'images/the-hobbit.jpg',
            'ebook' => 'First Book.epub',
            'chapters' => [
                ['title' => 'Opening Move', 'file' => 'chapter-01-opening-move.mp3', 'duration' => 2250.58],
                ['title' => 'Second Wind', 'file' => 'chapter-02-second-wind.mp3', 'duration' => 2128.48],
                ['title' => 'Third Time Lucky', 'file' => 'chapter-03-third-time-lucky.mp3', 'duration' => 2665.90],
                ['title' => 'Credits', 'file' => 'credits.mp3', 'duration' => 31.98, 'numbered' => false],
            ],
        ],
        'second' => [
            'title' => 'Second Book',
            'label' => 'Book 2',
            'narrator' => 'Second Reader',
            'cover' => 'images/the-hobbit.jpg',
            'chapters' => [
                ['title' => 'Homecoming', 'file' => 'chapter-01-homecoming.mp3', 'duration' => 1413.14],
            ],
        ],
        'third' => [
            'title' => 'Third Book',
            'label' => 'Book 3',
            'cover' => 'images/the-hobbit.jpg',
            'chapters' => [],
            'upcoming_chapters' => ['First Light', 'Last Stand'],
        ],
    ];

    /**
     * Use the test books from the start, so the routes are limited to them.
     */
    public function createApplication()
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $app->beforeBootstrapping(BootProviders::class, fn ($app) => $app['config']->set('audiobook.books', static::BOOKS));

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
