#!/usr/bin/env python3

"""Convert a Git tar stream into a byte-for-byte reproducible ZIP archive."""

from __future__ import annotations

import stat
import sys
import tarfile
import time
import zipfile
from pathlib import Path


def zip_datetime(epoch: int) -> tuple[int, int, int, int, int, int]:
    parts = time.gmtime(epoch)
    year = min(max(parts.tm_year, 1980), 2107)
    return (year, parts.tm_mon, parts.tm_mday, parts.tm_hour, parts.tm_min, parts.tm_sec)


def zip_info(
    member: tarfile.TarInfo,
    timestamp: tuple[int, int, int, int, int, int],
) -> zipfile.ZipInfo:
    name = member.name + ("/" if member.isdir() and not member.name.endswith("/") else "")
    info = zipfile.ZipInfo(name, timestamp)
    info.create_system = 3
    info.extra = b""
    info.comment = b""
    if member.isdir():
        info.compress_type = zipfile.ZIP_STORED
        info.external_attr = (stat.S_IFDIR | member.mode) << 16 | 0x10
    elif member.issym():
        info.compress_type = zipfile.ZIP_STORED
        info.external_attr = (stat.S_IFLNK | member.mode) << 16
    else:
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = (stat.S_IFREG | member.mode) << 16
    return info


def main() -> int:
    if len(sys.argv) != 3:
        raise SystemExit("usage: reproducible-zip.py <commit-epoch> <output>")
    epoch = int(sys.argv[1])
    output = Path(sys.argv[2])
    timestamp = zip_datetime(epoch)
    with tarfile.open(fileobj=sys.stdin.buffer, mode="r|") as source, zipfile.ZipFile(
        output,
        mode="w",
        compression=zipfile.ZIP_DEFLATED,
        compresslevel=9,
        strict_timestamps=True,
    ) as destination:
        for member in source:
            if not (member.isdir() or member.isreg() or member.issym()):
                continue
            info = zip_info(member, timestamp)
            if member.isdir():
                payload = b""
            elif member.issym():
                payload = member.linkname.encode("utf-8")
            else:
                extracted = source.extractfile(member)
                if extracted is None:
                    raise RuntimeError(f"cannot read archive member: {member.name}")
                payload = extracted.read()
            destination.writestr(info, payload, compresslevel=9)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
