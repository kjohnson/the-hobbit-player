# The Hobbit — Audiobook Player

A mobile-first Laravel site for listening to *The Hobbit* by J.R.R. Tolkien: all 19 chapters (about 9 hours),
narrated from the ebook with the [Kokoro-82M](https://huggingface.co/hexgrad/Kokoro-82M) text-to-speech model
(voice `bm_daniel` at 0.8 speed), with follow-along text for every chapter.

Copied from the After Moses audiobook player and themed for the Shire: moss green backgrounds, parchment text
and a brass-gold accent, with a round green door for the cover, app icons and favicon.

- Books and chapter list with per-chapter progress; finishing a book carries on into the next
- Resume where you left off (saved in the browser's localStorage)
- Back 15s / forward 30s
- Follow-along text: the ebook's text with the spoken word highlighted (tap a word to jump there)
- Works offline: the chapter that's playing and the next one are saved on the device automatically, and
  "Save all" in the chapters menu saves the whole book shown there. A service worker (`public/sw.js`) serves saved
  chapters, including seeking, and keeps the player itself available without a connection.
- Lock-screen and headphone controls via the Media Session API
- Installable to the home screen (web app manifest)

No database is required.

## Local development

```sh
composer install
npm install
cp .env.example .env && php artisan key:generate
composer run dev
```

> `php artisan serve` doesn't support HTTP range requests, so seeking in Safari/iOS only works against a real web server (Laravel Cloud, Herd, Valet, nginx).

## Narrating

`scripts/narrate.py` reads a chapter from `storage/app/private/the-hobbit/The Hobbit.epub` and writes its MP3 to
the book's `audio/` folder, with pauses between paragraphs and the lines of songs. It runs in its own Python
environment:

```sh
uv venv --python 3.11 ~/.venvs/kokoro
uv pip install --python ~/.venvs/kokoro/bin/python torch --index-url https://download.pytorch.org/whl/cpu
uv pip install --python ~/.venvs/kokoro/bin/python "kokoro>=0.9" soundfile pip

~/.venvs/kokoro/bin/python scripts/narrate.py 1              # bm_daniel at 0.8
~/.venvs/kokoro/bin/python scripts/narrate.py 1 --limit 5    # just the heading and first three paragraphs
~/.venvs/kokoro/bin/python scripts/narrate.py 1 --voice af_heart --speed 1.0
```

On a CPU it takes roughly half the length of the audio (much longer when other programs are busy). Stopping it
with Ctrl+C saves what's been narrated so far. After re-narrating a chapter, update its `duration` and redo its
follow-along text.

The ebook titles its chapters "Chapter I", "Chapter II" and so on, so each chapter has an `epub` key naming its
file in the ebook (also listed in `CHAPTERS` in the script). The book's folder:

```
storage/app/private/the-hobbit/
    The Hobbit.epub
    audio/chapter-01-an-unexpected-party.mp3, …
    transcripts/chapter-01-an-unexpected-party.json, …
    text/chapter-01.json, …              (written by audiobook:sync)
```

More books can be added the same way, each with its own entry in `books` (in reading order) and its own folder,
named after its key. The audio and transcripts aren't committed to git; the synced text is.

## Adding chapters

1. Drop the MP3 in the book's `audio/` folder (lowercase, no spaces, e.g. `chapter-04-some-title.mp3`).
2. Add an entry to the book's `chapters` with its title, file, and duration in seconds. Set `numbered` to
   `false` for tracks that aren't chapters, like the credits:

   ```sh
   ffprobe -v error -show_entries format=duration -of csv=p=0 storage/app/private/the-hobbit/audio/chapter-04-some-title.mp3
   ```

3. Upload it to the bucket with `php artisan audiobook:upload s3`.

## Follow-along text

Put a WhisperX JSON transcript of each chapter in the book's `transcripts/` folder, named after its MP3, and
run `php artisan audiobook:sync` (or `php artisan audiobook:sync the-hobbit 4` for one chapter of one book).
Chapters are found in the ebook by a "Chapter N" title matching their place in the list, or by their `epub`
key when the list doesn't line up with the ebook (e.g. it starts with opening credits, or the ebook titles its
chapters "Chapter IV" or only by name):

```sh
whisperx storage/app/private/the-hobbit/audio/chapter-04-over-hill-and-under-hill.mp3 --model small.en --language en \
    --compute_type int8 --output_format json --output_dir storage/app/private/the-hobbit/transcripts
php artisan audiobook:sync the-hobbit 4
```

The synced text lands in the book's `text/` folder. Chapters without it don't show the text button.

## Deploying to Laravel Cloud

1. Push this repo to GitHub/GitLab/Bitbucket and create an application in Laravel Cloud.
2. Build command: `composer install --no-dev && npm ci && npm run build && php artisan optimize`
3. Deploy command: leave empty (there are no migrations to run).
4. Environment variables: `SESSION_DRIVER=cookie`, `CACHE_STORE=file` (no database needed).

### Hosting the audio in Object Storage

The MP3s are too large for the repo, so they live in a private bucket. Locally they sit in
each book's `audio/` folder in `storage/app/private` (git-ignored), outside the web root. The player only
receives signed links (`/audio/{book}/{chapter}?expires=…&signature=…`) that expire after `AUDIOBOOK_URL_LIFETIME` minutes (default 360),
are rate limited, and are refused when opened directly in a browser tab or embedded on another site. To set
up the bucket:

1. Add a **private** Laravel Object Storage bucket to the environment and mark it as the **default disk**
   (any disk name). Redeploy so the bucket's connection is injected. Listeners are then redirected to
   short-lived pre-signed URLs.
2. Copy one of the bucket's access keys (Organization > Resources > Object storage > your bucket >
   Access keys > View credentials) into your local `.env`:

   ```dotenv
   AWS_ACCESS_KEY_ID=…
   AWS_SECRET_ACCESS_KEY=…
   AWS_BUCKET=…
   AWS_ENDPOINT=…
   AWS_DEFAULT_REGION=auto
   ```

3. Upload the files:

   ```sh
   php artisan audiobook:upload s3
   ```

   This copies each book's `audio/*.mp3` and `text/*.json` from local storage into the same folders in the bucket,
   skipping files that are already uploaded unchanged. Run it again after adding chapters or re-running
   `audiobook:sync`. The player checks for new text every 5 minutes.

`AUDIOBOOK_DISK` can name a disk other than the default, but isn't needed on Laravel Cloud.
