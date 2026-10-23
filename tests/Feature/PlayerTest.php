<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlayerTest extends TestCase
{
    public function test_player_lists_every_chapter(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('First Book')
            ->assertSeeInOrder(['Opening Move', 'Second Wind', 'Third Time Lucky', 'Credits'])
            ->assertSee('Homecoming');
    }

    public function test_player_lists_every_book_in_reading_order(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['First Book', 'Second Book', 'Third Book'])
            ->assertViewHas('books', fn ($books) => $books->pluck('slug')->all() === ['first', 'second', 'third']
                && $books->pluck('label')->all() === ['Book 1', 'Book 2', 'Book 3']);
    }

    public function test_a_book_can_name_its_chapters(): void
    {
        config(['audiobook.books.second.chapter_name' => 'Episode', 'audiobook.books.second.chapters.0.number' => 387]);

        $this->get('/')
            ->assertOk()
            ->assertViewHas('books', function ($books) {
                $book = $books->firstWhere('slug', 'second');

                return $book['chapterName'] === 'Episode'
                    && $book['chapters']->pluck('number')->all() === [387];
            });
    }

    public function test_a_book_without_chapters_is_listed_as_coming_soon(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Coming soon')
            ->assertSeeInOrder(['First Light', 'Last Stand'])
            ->assertDontSee('data-book="third"', false);
    }

    public function test_the_player_starts_with_a_book_that_has_chapters(): void
    {
        config(['audiobook.books' => ['third' => self::BOOKS['third'], 'first' => self::BOOKS['first']]]);

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['data-bind="chapter-label"', 'Chapter 1 of 3', 'data-bind="chapter-title"', 'Opening Move'], false);
    }

    public function test_the_player_is_coming_soon_until_a_book_has_chapters(): void
    {
        config(['audiobook.books' => ['third' => self::BOOKS['third']]]);

        $response = $this->get('/')->assertOk();

        $response->assertSeeInOrder(['data-bind="chapter-label"', 'Coming soon', 'data-bind="chapter-title"', 'Third Book'], false)
            ->assertSeeInOrder(['First Light', 'Last Stand'])
            ->assertSee('data-bind="read-by" hidden', false);
        $this->assertSame(6, substr_count($response->getContent(), ' disabled'));
    }

    public function test_the_narrator_is_shown_once_known(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Read by <span data-bind="narrator">First Reader</span>', false)
            ->assertDontSee('data-bind="read-by" hidden', false);
    }

    public function test_each_chapter_has_an_options_menu_for_its_progress(): void
    {
        $response = $this->get('/')->assertOk();
        $chapterCount = collect(config('audiobook.books'))->sum(fn (array $book): int => count($book['chapters']));

        $this->assertSame($chapterCount, substr_count($response->getContent(), 'data-chapter-options='));
        $this->assertSame($chapterCount, substr_count($response->getContent(), 'data-action="reset-progress"'));
        $response->assertSeeInOrder(['aria-label="Options for Opening Move"', 'id="actions-first-0"', 'Reset progress'], false)
            ->assertSee('aria-controls="actions-second-0"', false)
            ->assertDontSee('data-options-book="third"', false);
    }

    public function test_chapter_lengths_are_shown_in_minutes_and_seconds(): void
    {
        config(['audiobook.books' => [
            'first' => ['title' => 'First', 'label' => 'Book 1', 'narrator' => 'Reader', 'cover' => 'images/first.jpg', 'chapters' => [
                ['title' => 'Short', 'file' => 'short.mp3', 'duration' => 8.9],
                ['title' => 'Medium', 'file' => 'medium.mp3', 'duration' => 2250.5],
                ['title' => 'Long', 'file' => 'long.mp3', 'duration' => 4718.58],
            ]],
        ]]);

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Short', '00:08', 'Medium', '37:30', 'Long', '78:38']);
    }

    public function test_cover_urls_change_when_the_cover_does(): void
    {
        $hash = substr(hash_file('xxh3', public_path('images/the-hobbit.jpg')), 0, 8);

        $this->get('/')
            ->assertOk()
            ->assertViewHas('books', fn ($books) => $books->firstWhere('slug', 'second')['cover'] === asset('images/the-hobbit.jpg').'?v='.$hash);
    }

    public function test_player_links_to_signed_audio_urls_instead_of_files(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('.mp3')
            ->assertViewHas('books', function ($books) {
                $url = $books->first()['chapters']->first()['url'];

                return str_starts_with($url, url('/audio/first/1?expires='))
                    && str_contains($url, 'signature=');
            });
    }

    public function test_player_knows_each_chapters_download_size(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('first/audio/chapter-01-opening-move.mp3', str_repeat('a', 1234));

        $this->get('/')
            ->assertOk()
            ->assertViewHas('books', function ($books) {
                $chapters = $books->firstWhere('slug', 'first')['chapters'];

                return $chapters[0]['size'] === 1234 && $chapters[1]['size'] === null;
            });
    }

    public function test_tracks_that_are_not_chapters_are_left_unnumbered(): void
    {
        config(['audiobook.books' => [
            'first' => ['title' => 'First', 'label' => 'Book 1', 'narrator' => 'Reader', 'cover' => 'images/first.jpg', 'ebook' => 'first.epub', 'chapters' => [
                ['title' => 'Opening Credits', 'file' => 'opening-credits.mp3', 'duration' => 10.0, 'numbered' => false],
                ['title' => 'One', 'file' => 'chapter-01-one.mp3', 'duration' => 10.0],
                ['title' => 'Two', 'file' => 'chapter-02-two.mp3', 'duration' => 10.0],
                ['title' => 'Credits', 'file' => 'credits.mp3', 'duration' => 10.0, 'numbered' => false],
            ]],
            'second' => ['title' => 'Second', 'label' => 'Book 2', 'narrator' => 'Reader', 'cover' => 'images/second.jpg', 'ebook' => 'second.epub', 'chapters' => [
                ['title' => 'One', 'file' => 'chapter-01-one.mp3', 'duration' => 10.0],
            ]],
        ]]);

        $this->get('/')
            ->assertOk()
            ->assertViewHas('books', fn ($books) => $books[0]['chapters']->pluck('number')->all() === [null, 1, 2, null]
                && $books[1]['chapters']->pluck('number')->all() === [1]);
    }

    public function test_every_configured_chapter_file_exists(): void
    {
        $disk = Storage::disk(config('audiobook.disk'));

        foreach ($this->realBooks() as $book => $details) {
            if ($details['chapters'] === []) {
                continue;
            }

            if ($disk->files("{$book}/audio") === []) {
                $this->markTestSkipped("The audio is not in the repo; add it to storage/app/private/{$book}/audio to check it.");
            }

            foreach ($details['chapters'] as $chapter) {
                $this->assertTrue($disk->exists("{$book}/audio/{$chapter['file']}"), "Missing {$book}/audio/{$chapter['file']}");
            }
        }
    }

    public function test_audio_files_are_not_publicly_accessible(): void
    {
        foreach ($this->realBooks() as $book => $details) {
            foreach ($details['chapters'] as $chapter) {
                $this->assertFileDoesNotExist(public_path("audio/{$chapter['file']}"));
                $this->assertFileDoesNotExist(public_path("{$book}/audio/{$chapter['file']}"));
            }
        }
    }

    /**
     * The books in config/audiobook.php, rather than the test books.
     *
     * @return array<string, array<string, mixed>>
     */
    private function realBooks(): array
    {
        return (require config_path('audiobook.php'))['books'];
    }
}
