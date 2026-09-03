<?php

namespace App\Services;

/**
 * Convierte la respuesta de Lucas al formato adecuado para cada canal.
 *
 * Lucas/el modelo devuelve texto plano con marcado estilo WhatsApp
 * (*negrita*, _cursiva_, saltos \n, viñetas) y, en ocasiones, bloques <svg>
 * embebidos. Para la Web se convierte ese marcado a HTML seguro; para
 * WhatsApp se devuelve el texto plano tal cual.
 */
class ChatFormatter
{
    /**
     * Formatea la respuesta según el canal.
     *
     * @param string $text   Respuesta cruda del modelo.
     * @param string $source Canal de origen ('Web' o 'WhatsApp').
     */
    public function format(string $text, string $source = 'Web'): string
    {
        if ($source === 'WhatsApp') {
            // WhatsApp ya muestra texto plano: no se toca.
            return $text;
        }

        return $this->toHtml($text);
    }

    /**
     * Convierte texto con marcado estilo WhatsApp a HTML seguro para la Web.
     *
     * - Protege los bloques <svg> existentes para no corromperlos.
     * - Escapa el resto del HTML crudo.
     * - Convierte *negrita*, _cursiva_, saltos de línea y viñetas.
     */
    private function toHtml(string $text): string
    {
        // 1. Proteger bloques <svg>...</svg> reemplazándolos por marcadores.
        $svgs = [];
        $protected = preg_replace_callback(
            '/<svg[\s\S]*?<\/svg>/i',
            function (array $m) use (&$svgs): string {
                $token = "\x1E" . count($svgs) . "\x1E";
                $svgs[] = $m[0];

                return $token;
            },
            $text
        );

        // 2. Escapar HTML crudo (respeta los marcadores de SVG).
        $escaped = htmlspecialchars($protected ?? $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // 3. Negrita con *asteriscos* (sin confundir con listas).
        $escaped = $this->replaceBold($escaped);

        // 4. Cursiva con _guiones bajos_.
        $escaped = $this->replaceItalic($escaped);

        // 5. Viñetas y saltos de línea (las listas conservan su estructura sin
        //    que nl2br introduzca <br> dentro de <ul>).
        $escaped = $this->replaceLines($escaped);

        // 6. Restaurar los SVGs originales.
        if ($svgs !== []) {
            $escaped = preg_replace_callback(
                '/\x1E(\d+)\x1E/',
                function (array $m) use ($svgs): string {
                    return $svgs[(int) $m[1]];
                },
                $escaped
            );
        }

        return $escaped;
    }

    /**
     * Convierte *texto* y **texto** en <strong>.
     */
    private function replaceBold(string $text): string
    {
        // **texto** (doble asterisco)
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);

        // *texto* (asterisco simple, cualquier número de asteriscos restante)
        return preg_replace('/\*(.+?)\*/s', '<strong>$1</strong>', $text);
    }

    /**
     * Convierte _texto_ en <em>.
     */
    private function replaceItalic(string $text): string
    {
        return preg_replace('/_(.+?)_/s', '<em>$1</em>', $text);
    }

    /**
     * Recorre línea por línea: convierte viñetas (•, -, *) a listas <ul><li> y
     * añade saltos <br> entre las líneas de texto normales, sin contaminar la
     * estructura interna de las listas.
     */
    private function replaceLines(string $text): string
    {
        $lines = explode("\n", $text);
        $inList = false;
        $out = [];

        foreach ($lines as $line) {
            // Detecta viñeta al inicio de línea: "• ", "- ", "* ", "· "
            if (preg_match('/^\s*(?:[•·]|\-|\*)\s+/u', $line)) {
                $content = preg_replace('/^\s*(?:[•·]|\-|\*)\s+/u', '', $line);

                if (! $inList) {
                    $out[] = ['open_list', null];
                    $inList = true;
                }
                $out[] = ['item', $content];
            } else {
                if ($inList) {
                    $out[] = ['close_list', null];
                    $inList = false;
                }

                $out[] = ['text', $line];
            }
        }

        if ($inList) {
            $out[] = ['close_list', null];
        }

        // Construir el HTML final aplicando <br /> solo entre líneas de texto.
        $html = '';
        $prevText = false;

        foreach ($out as [$type, $value]) {
            if ($type === 'open_list') {
                if ($prevText) {
                    $html .= '<br />';
                }
                $html .= '<ul>';
                $prevText = false;
            } elseif ($type === 'close_list') {
                $html .= '</ul>';
                $prevText = false;
            } elseif ($type === 'item') {
                $html .= '<li>' . $value . '</li>';
                $prevText = false;
            } else {
                if ($prevText) {
                    $html .= '<br />';
                }
                if ($value !== '') {
                    $html .= $value;
                    $prevText = true;
                }
            }
        }

        return $html;
    }
}
