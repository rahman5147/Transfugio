<?php namespace EFrane\Transfugio\Http;

use EFrane\Transfugio\Transformers\EloquentWorker;
use EFrane\Transfugio\Web\WebView;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ResponseBuilder
{
    protected $responseFormatter = null;
    protected $options = [];

    public function __construct($format, array $options = [])
    {
        $this->responseFormatter = Formatter\FormatterFactory::get($format);
        $this->extractOptions($format, $options);
    }

    /**
     * @param $format
     * @param array $options
     **/
    protected function extractOptions($format, array $options)
    {
        $this->options = array_merge([
            'only'      => [],
            'format'    => $format,
            'includes'  => [],
            'modelName' => '',
        ], $options);
    }

    public function respondWithPaginated(LengthAwarePaginator $paginated, $status = 200)
    {
        $this->options['paginationCode'] = $paginated->render();

        $this->prepareEloquentResult($paginated);

        return $this->respond(collect($paginated), $status);
    }

    protected function prepareEloquentResult(&$result)
    {
        $worker = new EloquentWorker($this->options['includes']);

        $result = ($result instanceof LengthAwarePaginator)
            ? $worker->transformPaginated($result)
            : $worker->transformModel($result);
    }

    public function respond(Collection $data, $status = 200, $processed = false)
    {
        if (!$processed) {
            // remove unwanted output data
            if (count($this->options['only']) > 0) {
                $data = collect(array_diff_key($data->toArray(), $this->options['only']));
            }

            // TODO: add reusable request parameters to output
        }

        // transform to output format
        $data = $this->responseFormatter->format($data);

        return $this->generateResponse($data, $status);
    }

    /**
     * @param string $data
     * @param $status
     * @return mixed
     **/
    protected function generateResponse($data, $status)
    {
        $headers = ['Content-type' => $this->responseFormatter->getContentType()];

        if (config('transfugio.http.enableCORS')) {
            $headers['Access-Control-Allow-Origin'] = '*';
        }

        if ($this->options['format'] === 'html') {
            $response = new WebView($data, $status);

            $response->setModelName($this->options['modelName']);

            $response->setIsCollection(
                isset($this->options['paginationCode'])
                && strlen($this->options['paginationCode']) > 0
            );

            if (isset($this->options['paginationCode'])) {
                $response->setPaginationCode($this->options['paginationCode']);
            }

            $response->setIsError($status !== 200);

            $response->render();
        } else {
            $response = new Response($data, $status);
        }

        foreach ($headers as $name => $value) {
            $response->header($name, $value);
        }

        return $response;
    }

    public function respondWithModel(\Illuminate\Database\Eloquent\Model $item, $status = 200)
    {
        $this->prepareEloquentResult($item);

        return $this->respond(collect($item), $status);
    }

    /**
     * Return an empty response.
     *
     * APIs should never return nothing, thus a response stating that there is no
     * content is returned.
     *
     * The issue is, that the correct HTTP Status code for "No Content" is 204, which
     * is being used for all machine-readable output formats, however, if the
     * output will be a web view, 200 is set as status code in order to get
     * browsers to actually display the data.
     *
     * @param string $message
     * @param int $status
     * @return null
     */
    public function respondWithEmpty($message = "The requested result set is empty.", $status = 204)
    {
        if ($this->options['format'] === 'html') {
            $status = 200;
        }

        return $this->respondWithError($message, $status);
    }

    public function respondWithError($message = "An unknown error occurred, please try again later.", $status = 400)
    {
        $data = collect(['error' => ['message' => $message, 'status' => $status]]);

        return $this->respond($data, $status, true);
    }

    public function respondWithForbidden($message = "Access to this resource is forbidden.", $status = 403)
    {
        return $this->respondWithError($message, $status);
    }

    public function respondWithNotFound($message = "The requested resource was not found.", $status = 404)
    {
        return $this->respondWithError($message, $status);
    }

    public function respondWithNotAllowed($message = "Access to the requested resource is not allowed.", $status = 405)
    {
        return $this->respondWithError($message, $status);
    }
}