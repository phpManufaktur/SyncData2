<?php

/*
  JSON Format :: Creates nicely formatted json. Use in place of json_encode().
  Copyright (C) Sergey Tsalkov 2013
  License: LGPLv3

  USAGE:
    $j = new JSONFormat('  ', "\n"); // indent and linebreak characters
    $j->format(array("whatever", "more stuff")); // use just like you would json_encode()

    If you want to re-format an existing JSON object:
    $j->format('[{"a":"b","c": "d"}]', true);
*/

namespace phpManufaktur\SyncData\Control\JSON;

class JSONFormat
{

    public function __construct($indent = '  ', $linebreak = "\n")
    {
        $this->indent_char = $indent;
        $this->linebreak_char = $linebreak;
    }

    public function format($chunk, $already_json = false)
    {
        if ($already_json && is_string($chunk))
            $chunk = json_decode($chunk, true);

        if (is_array($chunk)) {
            if ($chunk === array_values($chunk))
                return $this->format_array($chunk);
            else
                return $this->format_hash($chunk);
        } else {
            return json_encode($chunk, true);
        }
    }

    protected function format_hash(array $hash)
    {
        $lines = array();
        foreach ($hash as $key => $value) {
            $lines[] = $this->format($key) . ': ' . $this->format($value);
        }
        return $this->format_multiline('{', $lines, '}');
    }

    protected function format_array(array $hash)
    {
        $lines = array();
        foreach ($hash as $value) {
            $lines[] = $this->format($value);
        }
        return $this->format_multiline('[', $lines, ']');
    }

    protected function format_multiline($startchar, array $lines, $endchar)
    {
        return $startchar . $this->indent($this->linebreak_char . implode(',' . $this->linebreak_char, $lines))
            . $this->linebreak_char . $endchar;
    }

    protected function indent($text)
    {
        return str_replace($this->linebreak_char, $this->linebreak_char . $this->indent_char, $text);
    }

}
