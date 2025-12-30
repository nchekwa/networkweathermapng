<?php
/**
 * Editor helper functions - adapted from Cacti plugin for standalone use
 */

function wm_editor_sanitize_uri($str) {
    static $drop_char_match = array(' ','^', '$', '<', '>', '`', '\'', '"', '|', '+', '[', ']', '{', '}', ';', '!', '%');
    static $drop_char_replace = array('', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '');
    return str_replace($drop_char_match, $drop_char_replace, urldecode($str));
}

function wm_editor_sanitize_string($str) {
    static $drop_char_match = array('<', '>');
    static $drop_char_replace = array('', '');
    return str_replace($drop_char_match, $drop_char_replace, htmlspecialchars($str, ENT_QUOTES, 'UTF-8'));
}

function wm_editor_sanitize_name($str) {
    return str_replace(array(' '), '', $str);
}

function wm_editor_sanitize_selected($str) {
    $res = urldecode($str);
    if (!preg_match("/^(LINK|NODE):/", $res)) {
        return "";
    }
    return wm_editor_sanitize_name($res);
}

function wm_editor_sanitize_conffile($filename) {
    $filename = wm_editor_sanitize_uri($filename);
    if (substr($filename, -5, 5) != ".conf") {
        $filename = "";
    }
    if (strstr($filename, "/") !== false) {
        $filename = "";
    }
    return $filename;
}

function wm_editor_sanitize_file($filename, $allowed_exts = array()) {
    $filename = wm_editor_sanitize_uri($filename);
    if ($filename == "") {
        return "";
    }
    $ok = false;
    foreach ($allowed_exts as $ext) {
        $match = "." . $ext;
        if (substr($filename, -strlen($match), strlen($match)) == $match) {
            $ok = true;
        }
    }
    if (!$ok) {
        return "";
    }
    return $filename;
}

function wm_editor_validate_bandwidth($bw) {
    if (preg_match('/^(\d+\.?\d*[KMGT]?)$/', $bw)) {
        return true;
    }
    return false;
}

function snap($coord, $gridsnap = 0) {
    if ($gridsnap == 0) {
        return $coord;
    } else {
        $rest = $coord % $gridsnap;
        return intval(($coord - $rest + round($rest / $gridsnap) * $gridsnap));
    }
}

function get_imagelist($basepath, $imagedir) {
    $imagelist = array();
    
    // Try new location first (src/public/objects or src/public/backgrounds)
    $imdir = $basepath . '/' . $imagedir;
    
    // Fallback to images subdirectory
    if (!is_dir($imdir)) {
        $imdir = $basepath . '/images/' . $imagedir;
    }
    
    if (is_dir($imdir)) {
        $dh = opendir($imdir);
        if ($dh) {
            while ($file = readdir($dh)) {
                $realfile = $imdir . '/' . $file;
                $uri = "$imagedir/$file";
                if (is_readable($realfile) && preg_match('/\.(gif|jpg|png)$/i', $file)) {
                    $imagelist[] = $uri;
                }
            }
            closedir($dh);
        }
    }
    
    sort($imagelist);
    return $imagelist;
}

function distance($ax, $ay, $bx, $by) {
    $dx = $bx - $ax;
    $dy = $by - $ay;
    return sqrt($dx * $dx + $dy * $dy);
}

function range_overlaps($a_min, $a_max, $b_min, $b_max) {
    if ($a_min > $b_max) return false;
    if ($b_min > $a_max) return false;
    return true;
}

function common_range($a_min, $a_max, $b_min, $b_max) {
    $min_overlap = max($a_min, $b_min);
    $max_overlap = min($a_max, $b_max);
    return array($min_overlap, $max_overlap);
}

function tidy_link(&$map, $target, $linknumber = 1, $linktotal = 1, $ignore_tidied = false) {
    if (isset($map->links[$target]) && isset($map->links[$target]->a)) {
        $node_a = $map->links[$target]->a;
        $node_b = $map->links[$target]->b;

        $new_a_offset = "0:0";
        $new_b_offset = "0:0";

        $bb_a = $node_a->boundingboxes[0] ?? array($node_a->x - 20, $node_a->y - 20, $node_a->x + 20, $node_a->y + 20);
        $bb_b = $node_b->boundingboxes[0] ?? array($node_b->x - 20, $node_b->y - 20, $node_b->x + 20, $node_b->y + 20);

        $x_overlap = range_overlaps($bb_a[0], $bb_a[2], $bb_b[0], $bb_b[2]);
        $y_overlap = range_overlaps($bb_a[1], $bb_a[3], $bb_b[1], $bb_b[3]);

        $a_x_offset = 0;
        $a_y_offset = 0;
        $b_x_offset = 0;
        $b_y_offset = 0;

        if (!$x_overlap && $y_overlap) {
            if ($bb_a[2] < $bb_b[0]) {
                $a_x_offset = $bb_a[2] - $node_a->x;
                $b_x_offset = $bb_b[0] - $node_b->x;
            }
            if ($bb_b[2] < $bb_a[0]) {
                $a_x_offset = $bb_a[0] - $node_a->x;
                $b_x_offset = $bb_b[2] - $node_b->x;
            }
            list($min_overlap, $max_overlap) = common_range($bb_a[1], $bb_a[3], $bb_b[1], $bb_b[3]);
            $overlap = $max_overlap - $min_overlap;
            $n = $overlap / ($linktotal + 1);
            $a_y_offset = $min_overlap + ($linknumber * $n) - $node_a->y;
            $b_y_offset = $min_overlap + ($linknumber * $n) - $node_b->y;
            $new_a_offset = sprintf("%d:%d", $a_x_offset, $a_y_offset);
            $new_b_offset = sprintf("%d:%d", $b_x_offset, $b_y_offset);
        }

        if (!$y_overlap && $x_overlap) {
            if ($bb_a[3] < $bb_b[1]) {
                $a_y_offset = $bb_a[3] - $node_a->y;
                $b_y_offset = $bb_b[1] - $node_b->y;
            }
            if ($bb_b[3] < $bb_a[1]) {
                $a_y_offset = $bb_a[1] - $node_a->y;
                $b_y_offset = $bb_b[3] - $node_b->y;
            }
            list($min_overlap, $max_overlap) = common_range($bb_a[0], $bb_a[2], $bb_b[0], $bb_b[2]);
            $overlap = $max_overlap - $min_overlap;
            $n = $overlap / ($linktotal + 1);
            $a_x_offset = $min_overlap + ($linknumber * $n) - $node_a->x;
            $b_x_offset = $min_overlap + ($linknumber * $n) - $node_b->x;
            $new_a_offset = sprintf("%d:%d", $a_x_offset, $a_y_offset);
            $new_b_offset = sprintf("%d:%d", $b_x_offset, $b_y_offset);
        }

        if (!$y_overlap && !$x_overlap) {
            $pt_a = new WMPoint($node_a->x, $node_a->y);
            $pt_b = new WMPoint($node_b->x, $node_b->y);
            $line = new WMLineSegment($pt_a, $pt_b);
            $tangent = $line->vector;
            $tangent->normalise();
            $normal = $tangent->getNormal();
            $pt_a->AddVector($normal, 15 * ($linknumber - 1));
            $pt_b->AddVector($normal, 15 * ($linknumber - 1));
            $a_x_offset = $pt_a->x - $node_a->x;
            $a_y_offset = $pt_a->y - $node_a->y;
            $b_x_offset = $pt_b->x - $node_b->x;
            $b_y_offset = $pt_b->y - $node_b->y;
            $new_a_offset = sprintf("%d:%d", $a_x_offset, $a_y_offset);
            $new_b_offset = sprintf("%d:%d", $b_x_offset, $b_y_offset);
        }

        $map->links[$target]->a_offset = $new_a_offset;
        $map->links[$target]->b_offset = $new_b_offset;
        if (method_exists($map->links[$target], 'add_hint')) {
            $map->links[$target]->add_hint('_tidied', 1);
        }
    }
}
