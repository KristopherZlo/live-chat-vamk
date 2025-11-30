from pathlib import Path
path=Path("resources/views/rooms/show.blade.php")
lines=path.read_text(encoding="utf-8").splitlines()
lines[242] = "                                            @foreach(['👍','❤️','😂','😮','👏','🔥'] as $emoji)"
path.write_text("\n".join(lines), encoding="utf-8")
