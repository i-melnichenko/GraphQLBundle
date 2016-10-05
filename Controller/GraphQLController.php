<?php
/**
 * Date: 25.11.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQLBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Youshido\GraphQL\Validator\Exception\ConfigurationException;
use Youshido\GraphQLBundle\Execution\Processor;

class GraphQLController extends Controller
{

    /**
     * @Route("/graphql")
     *
     * @throws ConfigurationException
     *
     * @return JsonResponse
     */
    public function defaultAction()
    {
        if ($this->get('request_stack')->getCurrentRequest()->getMethod() == 'OPTIONS') {
            return $this->createEmptyResponse();
        }

        list($query, $variables) = $this->getPayload();

        $schemaClass = $this->getParameter('graphql.schema_class');
        if (!$schemaClass || !class_exists($schemaClass)) {
            return $this->json([
                ['message' => 'Schema class ' . $schemaClass . ' does not exist']
            ]);
        }

        if (!$this->get('service_container')->initialized('graphql.schema')) {
            $schema = new $schemaClass();
            if ($schema instanceof ContainerAwareInterface) {
                $schema->setContainer($this->get('service_container'));
            }

            $this->get('service_container')->set('graphql.schema', $schema);
        }

        /** @var Processor $processor */
        $processor = $this->get('graphql.processor');
        $processor->processPayload($query, $variables);

        $response = $this->json($processor->getResponseData(), 200, $this->getParameter('graphql.response.headers'));

        if ($this->getParameter('graphql.response.json_pretty')) {
            $response->setEncodingOptions($response->getEncodingOptions() | JSON_PRETTY_PRINT);
        }

        return $response;
    }

    private function createEmptyResponse()
    {
        return $this->json([], 200, $this->getParameter('graphql.response.headers'));
    }

    private function getPayload()
    {
        $request   = $this->get('request_stack')->getCurrentRequest();
        $query     = $request->get('query', null);
        $variables = $request->get('variables', []);

        $variables = is_string($variables) ? json_decode($variables, true) ?: [] : [];

        $content = $request->getContent();
        if (!empty($content)) {
            $params = json_decode($content, true);

            if ($params) {
                $query = isset($params['query']) ? $params['query'] : $query;

                if (isset($params['variables'])) {
                    if (is_string($params['variables'])) {
                        $variables = json_decode($params['variables'], true) ?: $variables;
                    } else {
                        $variables = $params['variables'];
                    }

                    $variables = is_array($variables) ? $variables : [];
                }
            }
        }

        return [$query, $variables];
    }

    protected function json($data, $status = 200, $headers = [])
    {
        return new JsonResponse($data, $status, $headers);
    }
}
