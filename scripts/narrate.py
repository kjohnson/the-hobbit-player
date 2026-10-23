"""Narrate a chapter of the ebook with Kokoro (https://huggingface.co/hexgrad/Kokoro-82M).

    ~/.venvs/kokoro/bin/python scripts/narrate.py 1

reads chapter 1 from storage/app/private/the-hobbit/The Hobbit.epub and writes
storage/app/private/the-hobbit/audio/chapter-01-an-unexpected-party.mp3. Paragraphs, the lines
of songs and the chapter heading each get their own pause. Pass --limit to narrate only the first
few blocks as a sample; stopping with Ctrl+C keeps what's been narrated so far.

Pass --cast to give characters their own voices, e.g. --cast scripts/casts/chapter-01.json, which
writes chapter-01-an-unexpected-party-full-cast.mp3. A cast file maps characters to voices, and
blocks (numbered from 0, in reading order) to whoever speaks their quotes: one name for every quote
in the block, or a list with one per quote, null leaving a quote to the narrator.
"""

import argparse
import json
import re
import subprocess
import tempfile
import zipfile
from html import unescape
from pathlib import Path

import numpy as np
import soundfile as sf
from kokoro import KPipeline

ROOT = Path(__file__).resolve().parent.parent
BOOK = ROOT / 'storage/app/private/the-hobbit'
SAMPLE_RATE = 24000

# Chapter number → (file in the ebook, title), matching config/audiobook.php.
CHAPTERS = {
    1: ('text/part0006.html', 'An Unexpected Party'),
    2: ('text/part0007.html', 'Roast Mutton'),
    3: ('text/part0008.html', 'A Short Rest'),
    4: ('text/part0009.html', 'Over Hill and Under Hill'),
    5: ('text/part0010.html', 'Riddles in the Dark'),
    6: ('text/part0011.html', 'Out of the Frying-Pan into the Fire'),
    7: ('text/part0012.html', 'Queer Lodgings'),
    8: ('text/part0013.html', 'Flies and Spiders'),
    9: ('text/part0014.html', 'Barrels Out of Bond'),
    10: ('text/part0015.html', 'A Warm Welcome'),
    11: ('text/part0016.html', 'On the Doorstep'),
    12: ('text/part0017.html', 'Inside Information'),
    13: ('text/part0018.html', 'Not at Home'),
    14: ('text/part0019.html', 'Fire and Water'),
    15: ('text/part0020.html', 'The Gathering of the Clouds'),
    16: ('text/part0021.html', 'A Thief in the Night'),
    17: ('text/part0022.html', 'The Clouds Burst'),
    18: ('text/part0023.html', 'The Return Journey'),
    19: ('text/part0024.html', 'The Last Stage'),
}

NUMBERS = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
           'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen']

# Seconds of silence after each kind of block.
PAUSES = {'heading': 0.9, 'title': 1.6, 'paragraph': 0.6, 'verse': 0.35, 'stanza': 0.9}

# Each voice's speaking speed, where 1.0 isn't right for it.
SPEEDS = {'bm_daniel': 0.8, 'bm_fable': 0.8, 'bm_george': 0.8, 'bm_lewis': 0.8}

# Seconds of silence between the narration and a quote within a block.
TURN_PAUSE = 0.15


def clean(html):
    text = unescape(re.sub(r'<[^>]+>', '', html))

    return re.sub(r'\s+', ' ', text).strip()


def blocks(html, number, title):
    """The chapter as (kind, text) pairs, in reading order."""
    yield 'heading', f'Chapter {NUMBERS[number]}.'
    yield 'title', f'{title}.'

    paragraphs = re.findall(r'<p class="([^"]*)"[^>]*>(.*?)</p>', html, re.S)

    for i, (cls, body) in enumerate(paragraphs):
        text = clean(body)

        if not text:
            continue

        if cls.startswith('vext'):
            following = paragraphs[i + 1][0] if i + 1 < len(paragraphs) else ''
            # A "vext" line starts the next stanza, and prose ends the song.
            yield ('stanza' if following == 'vext' or not following.startswith('vext') else 'verse'), text
        else:
            yield 'paragraph', text


def voiced(text, speakers, narrator, voices):
    """Split a block into (voice, text) parts, giving each quote to its speaker's voice."""
    if speakers is None:
        return [(narrator, text)]

    # A quote runs to its closing mark, or to the end of the block when the speech goes on into the next.
    parts = re.findall(r'(“[^”]*(?:”|$))|([^“]+)', text)
    quotes = sum(1 for quote, _ in parts if quote)
    speakers = speakers if isinstance(speakers, list) else [speakers] * quotes
    assert len(speakers) == quotes, f'{len(speakers)} speakers for {quotes} quotes in: {text[:60]}'
    speakers = iter(speakers)
    result = []

    for quote, narration in parts:
        voice = voices[speaker] if quote and (speaker := next(speakers)) else narrator
        part = (quote or narration).strip(' ,—')

        if re.search(r'\w', part):
            result.append((voice, part))

    return result


def describe(args, cast):
    """The voices used, for the MP3's comment tag."""
    roles = [('narrator', args.voice), *cast['voices'].items()]

    return ', '.join(f"{voice}{f' as {role}' if cast['voices'] else ''} at {args.speed or SPEEDS.get(voice, 1.0)}" for role, voice in roles)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('chapter', type=int, choices=CHAPTERS)
    parser.add_argument('--voice', default='bm_daniel')
    parser.add_argument('--speed', type=float, help="defaults to the voice's entry in SPEEDS, or 1.0")
    parser.add_argument('--cast', type=Path, help='a cast file giving characters their own voices')
    parser.add_argument('--limit', type=int, help='only narrate this many blocks (paragraphs, song lines, the heading)')
    args = parser.parse_args()

    cast = json.loads(args.cast.read_text()) if args.cast else {'voices': {}, 'lines': {}}
    file, title = CHAPTERS[args.chapter]
    html = zipfile.ZipFile(BOOK / 'The Hobbit.epub').read(file).decode('utf-8')
    slug = re.sub(r'[^a-z0-9]+', '-', title.lower()).strip('-')
    out = BOOK / 'audio' / f"chapter-{args.chapter:02d}-{slug}{'-full-cast' if args.cast else ''}.mp3"

    # American voices start with "a", British ones with "b".
    pipeline = KPipeline(lang_code=args.voice[0], repo_id='hexgrad/Kokoro-82M')
    parts = []
    items = list(blocks(html, args.chapter, title))[:args.limit]

    try:
        for n, (kind, text) in enumerate(items, 1):
            for i, (voice, part) in enumerate(voiced(text, cast['lines'].get(str(n - 1)), args.voice, cast['voices'])):
                speed = args.speed or SPEEDS.get(voice, 1.0)
                pause = [np.zeros(int(TURN_PAUSE * SAMPLE_RATE), dtype=np.float32)] if i else []
                parts += pause + [result.audio.numpy() for result in pipeline(part, voice=voice, speed=speed, split_pattern=None)]

            parts.append(np.zeros(int(PAUSES[kind] * SAMPLE_RATE), dtype=np.float32))
            print(f'\r{n}/{len(items)} blocks', end='', flush=True)
    except KeyboardInterrupt:
        print(' — stopped, saving what was narrated', end='')

    print()

    if not parts:
        return

    with tempfile.NamedTemporaryFile(suffix='.wav') as wav:
        sf.write(wav.name, np.concatenate(parts), SAMPLE_RATE)
        subprocess.run([
            'ffmpeg', '-y', '-loglevel', 'error', '-i', wav.name,
            '-codec:a', 'libmp3lame', '-b:a', '64k', '-ac', '1',
            '-metadata', f'title={title}', '-metadata', 'artist=J.R.R. Tolkien',
            '-metadata', 'album=The Hobbit', '-metadata', f'track={args.chapter}',
            '-metadata', f'comment=Narrated by Kokoro-82M ({describe(args, cast)})',
            str(out),
        ], check=True)

    print(out)


if __name__ == '__main__':
    main()
