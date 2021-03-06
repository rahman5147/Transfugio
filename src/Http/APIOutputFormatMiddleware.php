<?php namespace EFrane\Transfugio\Http;

use Illuminate\Http\Request;

class APIOutputFormatMiddleware
{
    public function handle($request, \Closure $next)
    {
        config(['transfugio.http.format' => $this->determineOutputFormat($request)]);

        return $next($request);
    }

    private function determineOutputFormat(Request $request)
    {
        if ($request->wantsJson()) {
            return 'json_accept';
        }

        switch ($request->input('format')) {
            case 'json':
                return 'json';
            case 'xml':
                return 'xml';
            case 'yaml':
                return 'yaml';
            case 'html':
                return 'html';

            default:
                return 'json_accept';
        }
    }
}