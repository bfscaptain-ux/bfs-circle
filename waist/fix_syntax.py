with open("employees_list.php", "r", encoding="utf-8") as f:
    html = f.read()

html = html.replace("$_SESSION[\'role\']", "$_SESSION['role']")
html = html.replace("?? \'\') === \'Admin\'", "?? '') === 'Admin'")
html = html.replace("echo $emp[\'id\'];", "echo $emp['id'];")
html = html.replace("$emp[\'full_name\']", "$emp['full_name']")
html = html.replace("\\'Admin\\'", "'Admin'")
html = html.replace("\\'role\\'", "'role'")
html = html.replace("\\'id\\'", "'id'")
html = html.replace("\\'full_name\\'", "'full_name'")

# Just clean up any stray backslashes inside PHP tags in that block
html = html.replace("$_SESSION[\\'role\\']", "$_SESSION['role']")
html = html.replace("?? \\'\\') === \\'Admin\\'", "?? '') === 'Admin'")
html = html.replace("echo $emp[\\'id\\'];", "echo $emp['id'];")
html = html.replace("$emp[\\'full_name\\']", "$emp['full_name']")

with open("employees_list.php", "w", encoding="utf-8") as f:
    f.write(html)
