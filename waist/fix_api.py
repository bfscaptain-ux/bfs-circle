with open("api.php", "r", encoding="utf-8") as f:
    api = f.read()

# Replace the broken line
api = api.replace('$replace = $search . "\n        "" . $name . "",";', '$replace = $search . "\\n        \\"" . $name . "\\",";')

with open("api.php", "w", encoding="utf-8") as f:
    f.write(api)
