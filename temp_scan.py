from pathlib import Path
import re
text = Path(r"resources/views/rooms/show.blade.php").read_text(encoding="utf-8")
positions = [m.start() for m in re.finditer(r'<div class="message-actions">', text)]
print(positions)
