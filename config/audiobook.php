<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Series Details
    |--------------------------------------------------------------------------
    */

    'series' => 'The Hobbit',

    'author' => 'J.R.R. Tolkien',

    /*
    |--------------------------------------------------------------------------
    | Audio Location
    |--------------------------------------------------------------------------
    |
    | Each book has its own folder (named after its key below) on a private
    | disk, holding its ebook and its "audio", "text" and "transcripts"
    | folders. Nothing is served from a public URL. On the local disk audio
    | is streamed through a signed route; on S3-compatible object storage
    | (e.g. a private Laravel Cloud bucket) listeners are redirected to a
    | short-lived pre-signed URL.
    |
    | Leave AUDIOBOOK_DISK unset to use the default disk: "local" here, and
    | the bucket attached as the default disk on Laravel Cloud.
    |
    | Signed player URLs expire after "url_lifetime" minutes. The player
    | fetches a fresh one automatically if a listener comes back later.
    |
    */

    'disk' => env('AUDIOBOOK_DISK'),

    'url_lifetime' => (int) env('AUDIOBOOK_URL_LIFETIME', 360),

    /*
    |--------------------------------------------------------------------------
    | Books
    |--------------------------------------------------------------------------
    |
    | In reading order. The key names the book's folder and appears in its
    | URLs. Finishing a book carries on into the next. The "label" shows
    | above the book's title in the player. A book with no chapters yet is
    | shown as coming soon, listing its "upcoming_chapters" titles.
    |
    | Running "php artisan audiobook:sync" matches each chapter's text in the
    | ebook against a WhisperX transcript of its audio (in "transcripts") and
    | writes word timings to the "text" folder, which the player reads.
    | Chapters are found in the ebook by a "Chapter N" title matching their
    | place in the list. Add an "epub" key to a chapter to point at a
    | specific file instead, e.g. when the list starts with opening credits
    | or the ebook doesn't title its chapters "Chapter N".
    |
    | Durations are in seconds, so the chapter list can show lengths before
    | any audio has been loaded. Get them with:
    | ffprobe -v error -show_entries format=duration -of csv=p=0 file.mp3
    |
    | Set "numbered" to false for tracks that aren't chapters, like the
    | credits, so they're listed without a chapter number. A chapter's
    | "number" can be set instead, and a book's "chapter_name" names its
    | chapters (e.g. "Episode" instead of "Chapter"). Leave out "narrator"
    | until it's known.
    |
    */

    'books' => [
        'the-hobbit' => [
            'title' => 'The Hobbit',
            'label' => 'There and Back Again',
            'narrator' => 'Kokoro',
            'cover' => 'images/the-hobbit.jpg',
            'ebook' => 'The Hobbit.epub',
            'chapters' => [
                [
                    'title' => 'An Unexpected Party',
                    'file' => 'chapter-01-an-unexpected-party.mp3',
                    'epub' => 'text/part0006.html',
                    'duration' => 2991.48,
                ],
                [
                    'title' => 'Roast Mutton',
                    'file' => 'chapter-02-roast-mutton.mp3',
                    'epub' => 'text/part0007.html',
                    'duration' => 1813.63,
                ],
                [
                    'title' => 'A Short Rest',
                    'file' => 'chapter-03-a-short-rest.mp3',
                    'epub' => 'text/part0008.html',
                    'duration' => 1006.03,
                ],
                [
                    'title' => 'Over Hill and Under Hill',
                    'file' => 'chapter-04-over-hill-and-under-hill.mp3',
                    'epub' => 'text/part0009.html',
                    'duration' => 1369.32,
                ],
                [
                    'title' => 'Riddles in the Dark',
                    'file' => 'chapter-05-riddles-in-the-dark.mp3',
                    'epub' => 'text/part0010.html',
                    'duration' => 2459.11,
                ],
                [
                    'title' => 'Out of the Frying-Pan into the Fire',
                    'file' => 'chapter-06-out-of-the-frying-pan-into-the-fire.mp3',
                    'epub' => 'text/part0011.html',
                    'duration' => 2264.54,
                ],
                [
                    'title' => 'Queer Lodgings',
                    'file' => 'chapter-07-queer-lodgings.mp3',
                    'epub' => 'text/part0012.html',
                    'duration' => 2999.71,
                ],
                [
                    'title' => 'Flies and Spiders',
                    'file' => 'chapter-08-flies-and-spiders.mp3',
                    'epub' => 'text/part0013.html',
                    'duration' => 3315.55,
                ],
                [
                    'title' => 'Barrels Out of Bond',
                    'file' => 'chapter-09-barrels-out-of-bond.mp3',
                    'epub' => 'text/part0014.html',
                    'duration' => 1944.98,
                ],
                [
                    'title' => 'A Warm Welcome',
                    'file' => 'chapter-10-a-warm-welcome.mp3',
                    'epub' => 'text/part0015.html',
                    'duration' => 1297.82,
                ],
                [
                    'title' => 'On the Doorstep',
                    'file' => 'chapter-11-on-the-doorstep.mp3',
                    'epub' => 'text/part0016.html',
                    'duration' => 981.43,
                ],
                [
                    'title' => 'Inside Information',
                    'file' => 'chapter-12-inside-information.mp3',
                    'epub' => 'text/part0017.html',
                    'duration' => 2340.38,
                ],
                [
                    'title' => 'Not at Home',
                    'file' => 'chapter-13-not-at-home.mp3',
                    'epub' => 'text/part0018.html',
                    'duration' => 1326.41,
                ],
                [
                    'title' => 'Fire and Water',
                    'file' => 'chapter-14-fire-and-water.mp3',
                    'epub' => 'text/part0019.html',
                    'duration' => 1085.14,
                ],
                [
                    'title' => 'The Gathering of the Clouds',
                    'file' => 'chapter-15-the-gathering-of-the-clouds.mp3',
                    'epub' => 'text/part0020.html',
                    'duration' => 1170.00,
                ],
                [
                    'title' => 'A Thief in the Night',
                    'file' => 'chapter-16-a-thief-in-the-night.mp3',
                    'epub' => 'text/part0021.html',
                    'duration' => 726.43,
                ],
                [
                    'title' => 'The Clouds Burst',
                    'file' => 'chapter-17-the-clouds-burst.mp3',
                    'epub' => 'text/part0022.html',
                    'duration' => 1337.83,
                ],
                [
                    'title' => 'The Return Journey',
                    'file' => 'chapter-18-the-return-journey.mp3',
                    'epub' => 'text/part0023.html',
                    'duration' => 970.03,
                ],
                [
                    'title' => 'The Last Stage',
                    'file' => 'chapter-19-the-last-stage.mp3',
                    'epub' => 'text/part0024.html',
                    'duration' => 972.58,
                ],
                [
                    'title' => 'An Unexpected Party (Full Cast)',
                    'file' => 'chapter-01-an-unexpected-party-full-cast.mp3',
                    'epub' => 'text/part0006.html',
                    'duration' => 3381.48,
                    'numbered' => false,
                ],
            ],
        ],
    ],

];
