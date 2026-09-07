<?php

namespace App\Services;

/**
 * Adapta la respuesta de Lucas al canal correspondiente.
 *
 * Lucas NO devuelve Markdown: según el canal devuelve directamente:
 *   - Web     → HTML bien formado (<p>, <strong>, <ul>, <table>, <svg>, ...)
 *   - WhatsApp → marcado de WhatsApp (*negrita*, _cursiva_, viñetas •, saltos)
 *
 * Por eso este servicio NO re-convierte el texto: solo:
 *   - Para Web: devuelve el HTML tal cual (el frontend ya lo sanitiza con
 *     DOMPurify vía el pipe `safeHtml`), protegiendo los bloques <svg>.
 *   - Para WhatsApp: devuelve el texto plano tal cual.
 *
 * Adicionalmente expone `extractSuggestions()` para rescatar las sugerencias
 * que Lucas incluye dentro del propio `message` (bloque "---SUGGESTIONS---").
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
            // WhatsApp ya muestra texto plano con marcado propio: no se toca.
            return $text;
        }

        // Web: Lucas entrega HTML bien formado; solo se quita el bloque de
        // sugerencias incrustado y se devuelve el HTML. El frontend sanitiza.
        return $this->stripEmbeddedSuggestions($text);
    }

    /**
     * Elimina el bloque "---SUGGESTIONS---" que Lucas pueda incrustar dentro
     * del HTML/mensaje, dejando el contenido limpio.
     */
    private function stripEmbeddedSuggestions(string $text): string
    {
        return preg_replace(
            '/--+\s*SUGGESTIONS?\s*--+[\s\S]*$/i',
            '',
            $text
        ) ?? $text;
    }

    /**
     * Extrae las sugerencias que Lucas incluye dentro del propio `message`
     * bajo un separador "---SUGGESTIONS---" (o simplemente como preguntas al
     * final), dejando el `message` sin ese bloque.
     *
     * Si Lucas ya devuelve un array `suggestions` por separado, éste no se
     * usa; aquí solo se rescatan las que Lucas mete dentro del texto.
     *
     * @return array{message: string, suggestions: string[]}
     */
    public function extractSuggestions(string $text): array
    {
        $suggestions = [];
        $body = $text;

        if (preg_match('/--+\s*SUGGESTIONS?\s*--+/i', $text, $m, PREG_OFFSET_CAPTURE)) {
            $body = substr($text, 0, $m[0][1]);
            $rest = substr($text, $m[0][1] + strlen($m[0][0]));

            // Cada línea no vacía posterior a la marca se toma como sugerencia.
            $lines = preg_split('/\R/', $rest);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                // Quitar guiones/asteriscos/espacios ASCII únicamente (no
                // multibyte: ltrim byte a byte rompería caracteres como "¿").
                $line = ltrim($line, '-* ');
                $line = trim($line);
                if ($line !== '' && $this->looksLikeQuestion($line)) {
                    $suggestions[] = $line;
                }
            }
        }

        return [
            'message' => trim($body),
            'suggestions' => $suggestions,
        ];
    }

    /**
     * True si la línea es una pregunta o sugerencia razonable (comienza con
     * "¿" o con verbo interrogativo).
     */
    private function looksLikeQuestion(string $line): bool
    {
        if (str_starts_with($line, '¿')) {
            return true;
        }

        return (bool) preg_match('/^(?:quisieras|quieres|necesitas|te muestro|puedo|podrias|podrías|te gustaria|te gustaría)\b/i', $line);
    }
}
