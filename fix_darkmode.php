<?php
$files = glob("*.php");
foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Replace background colors that break dark mode
    $content = str_replace('bg-body', 'bg-body', $content);
    $content = str_replace('bg-body-tertiary', 'bg-body-tertiary', $content);
    
    // Replace text colors that break dark mode, EXCEPT inside buttons or badges where the background is forced
    // e.g., btn-warning text-dark should stay text-dark.
    // Instead of regex, I'll replace specific known bad classes
    $content = str_replace('text-body', 'text-body', $content);
    $content = str_replace('text-body-secondary', 'text-body-secondary', $content);
    
    // For text-dark, let's replace "text-body border-0" -> "text-body border-0", "text-dark"> -> "text-body">
    $content = str_replace('text-body border-0', 'text-body border-0', $content);
    $content = str_replace('text-body"><i', 'text-body"><i', $content);
    $content = str_replace('fw-bold text-body">', 'fw-bold text-body">', $content);
    
    // Table headers
    $content = str_replace('class="table-group-divider"', 'class="table-group-divider"', $content);
    $content = str_replace('class="table-group-divider sticky-top"', 'class="table-group-divider sticky-top"', $content);
    $content = str_replace('class=\'table-light\'', 'class=\'table-group-divider\'', $content);
    
    file_put_contents($file, $content);
}
echo "Replaced classes successfully!";
?>
