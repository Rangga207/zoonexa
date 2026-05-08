import os
import re

dir_path = '/Applications/XAMPP/xamppfiles/htdocs/zoonexa'

php_files = [f for f in os.listdir(dir_path) if f.endswith('.php')]

header_content = ""
footer_content = ""

if 'header.php' in php_files:
    header_content = open(os.path.join(dir_path, 'header.php'), 'r').read()
if 'footer.php' in php_files:
    footer_content = open(os.path.join(dir_path, 'footer.php'), 'r').read()

def strip_php(text):
    text = re.sub(r'<\?php.*?\?>', '', text, flags=re.DOTALL)
    text = re.sub(r'<\?=.*?\?>', '', text, flags=re.DOTALL)
    return text

def convert_links(text):
    text = re.sub(r'href="([^"]+)\.php"', r'href="\1.html"', text)
    text = re.sub(r"href='([^']+)\.php'", r"href='\1.html'", text)
    text = re.sub(r'action="([^"]+)\.php"', r'action="\1.html"', text)
    return text

header_html = convert_links(strip_php(header_content))
footer_html = convert_links(strip_php(footer_content))

for f in php_files:
    if f in ['config.php', 'logout.php', 'header.php', 'footer.php']:
        continue
        
    content = open(os.path.join(dir_path, f), 'r').read()
    
    # Replace block with header
    def header_replacer(match):
        if 'header.php' in match.group(0):
            return header_html
        return match.group(0)
    
    content = re.sub(r'<\?php.*?\?>', header_replacer, content, count=1, flags=re.DOTALL)
    
    # Replace block with footer
    def footer_replacer(match):
        if 'footer.php' in match.group(0):
            return footer_html
        return match.group(0)
        
    content = re.sub(r'<\?php.*?\?>', footer_replacer, content, flags=re.DOTALL)
    
    # Strip remaining PHP
    content = strip_php(content)
    
    # Convert links
    content = convert_links(content)
    
    html_filename = f.replace('.php', '.html')
    with open(os.path.join(dir_path, html_filename), 'w') as out_file:
        out_file.write(content.strip() + '\n')

with open(os.path.join(dir_path, 'header.html'), 'w') as out_file:
    out_file.write(header_html.strip() + '\n')
with open(os.path.join(dir_path, 'footer.html'), 'w') as out_file:
    out_file.write(footer_html.strip() + '\n')

print("Conversion 2.0 complete.")
