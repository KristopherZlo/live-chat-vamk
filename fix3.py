import re
from pathlib import Path
path = Path("resources/views/rooms/show.blade.php")
text = path.read_text(encoding="utf-8")
pattern = r"@foreach\(['\\U0001f44d','\\u2764\\ufe0f','\\U0001f602','\\U0001f62e','\\U0001f44f','\\U0001f525'] as \$emoji\)"
text, n = re.subn(pattern, "@foreach(['👍','❤️','😂','😮','👏','🔥'] as $emoji)", text)
print('replaced', n)
path.write_text(text, encoding="utf-8")
