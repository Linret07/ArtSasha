#!/usr/bin/env python3
import argparse
from pathlib import Path
from PIL import Image
import time

def parse_args():
    p = argparse.ArgumentParser(description='Resize an image by a scale factor')
    p.add_argument('image', help='Path to image file')
    p.add_argument('--factor', type=float, default=1.5, help='Scale factor (e.g., 1.5 for +50%%)')
    p.add_argument('--backup', action='store_true', help='Create a backup before overwriting')
    return p.parse_args()


def main():
    args = parse_args()
    img_path = Path(args.image)
    if not img_path.exists():
        print(f'Error: {img_path} does not exist')
        return 2
    if args.backup:
        stamp = time.strftime('%Y%m%d%H%M%S')
        backup = img_path.with_name(img_path.stem + f'.backup.{stamp}' + img_path.suffix)
        img_path.replace(backup)
        src_path = backup
        print(f'Created backup: {backup}')
    else:
        src_path = img_path

    with Image.open(src_path) as im:
        w, h = im.size
        nw = max(1, int(round(w * args.factor)))
        nh = max(1, int(round(h * args.factor)))
        resized = im.resize((nw, nh), Image.LANCZOS)
        # Save to target path (overwrite original path)
        resized.save(img_path)
        print(f'Resized {src_path} ({w}x{h}) -> {img_path} ({nw}x{nh})')
    return 0

if __name__ == '__main__':
    raise SystemExit(main())
