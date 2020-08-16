<?php

namespace Stillat\Meerkat\Tags;

use Illuminate\Contracts\Container\BindingResolutionException;
use Statamic\Tags\Tags;
use Stillat\Meerkat\Addon as MeerkatAddon;
use Stillat\Meerkat\Concerns\GetsHiddenContext;
use Stillat\Meerkat\Core\Contracts\Data\DataSetContract;
use Stillat\Meerkat\Core\Contracts\Parsing\SanitationManagerContract;
use Stillat\Meerkat\Core\Contracts\Threads\ContextResolverContract;
use Stillat\Meerkat\Core\Contracts\Threads\ThreadManagerContract;
use Stillat\Meerkat\Core\Data\DataQuery;
use Stillat\Meerkat\Core\Exceptions\FilterException;
use Stillat\Meerkat\Exceptions\TemplateTagsException;
use Stillat\Meerkat\Forms\MeerkatForm;
use Stillat\Meerkat\PathProvider;
use Stillat\Meerkat\Tags\Responses\CollectionRenderer;
use Stillat\Meerkat\Tags\Testing\OutputThreadDebugInformation;

class Meerkat extends Tags
{
    use GetsHiddenContext;

    private $threadManager = null;

    private $sanitizer = null;

    /**
     * The context resolver implementation instance.
     *
     * @var ContextResolverContract
     */
    private $contextResolver = null;

    public function __construct(ThreadManagerContract $threadManager, SanitationManagerContract $sanitizer)
    {
        $this->threadManager = $threadManager;
        $this->sanitizer = $sanitizer;
    }

    /**
     * {{ meerkat }}
     *
     * @return string
     */
    public function index()
    {
        return '';
    }

    /**
     * Renders a Meerkat form.
     *
     * Maps to {{ meerkat:create }}
     * Alias of {{ meerkat:form }}
     *
     * @return string
     * @throws BindingResolutionException
     */
    public function create()
    {
        return $this->form();
    }

    /**
     * Renders a Meerkat form.
     *
     * Maps to {{ meerkat:form }}
     *
     * @return string
     * @throws BindingResolutionException
     */
    public function form()
    {
        return $this->renderDynamic(MeerkatForm::class);
    }

    public function debug()
    {
        return $this->renderDynamic(OutputThreadDebugInformation::class);
    }

    /**
     * @param $className
     * @param null $instanceCallback
     * @return string
     * @throws BindingResolutionException
     * @throws TemplateTagsException
     */
    private function renderDynamic($className, $instanceCallback = null)
    {
        if ($className !== null && mb_strlen(trim($className)) > 0) {
            /** @var MeerkatTag $instance */
            $instance = app()->make($className);
            $instance->setFromContext($this);

            if ($instanceCallback !== null && is_callable($instanceCallback)) {
                $instance = $instanceCallback($instance);

                if ($instance === null || ($instance instanceof MeerkatTag) === false) {
                    throw new TemplateTagsException('Instance callback must return instance of ' . $className);
                }
            }

            return $instance->render();
        }

        return '';
    }

    /**
     * Returns a value indicating if comments are enabled for the current page context.
     *
     * {{ meerkat:comments-enabled }}
     *
     * @return bool
     */
    public function commentsEnabled()
    {
        return $this->threadManager->areCommentsEnabledForContext($this->getHiddenContext());
    }

    /**
     * Returns the number of published, not-spam comments.
     *
     * @return int
     */
    public function commentCount()
    {
        // TODO: Allow override of query. Needs a global "query builder builder".
        $contextId = $this->getHiddenContext();
        $thread = $this->threadManager->findById($contextId);

        if ($thread === null) {
            return 0;
        }

        /** @var DataSetContract $queryResults */
        $queryResults = $thread->query(function (DataQuery $builder) {
            return $builder->filterBy('is:spam(false)')->thenFilterBy('is:published(true)');
        });

        return $queryResults->count();
    }

    /**
     * {{ meerkat:all-comments }}
     *
     * @return string
     */
    public function allComments()
    {
        // TODO: Implement.
        return '';
    }

    /**
     * {{ meerkat:responses }}
     *
     * @return string|string[]
     * @throws BindingResolutionException
     * @throws FilterException
     */
    public function responses()
    {
        $contextId = $this->getHiddenContext();

        if ($contextId === null || mb_strlen(trim($contextId)) === 0) {
            return '';
        }

        return $this->renderDynamic(
            CollectionRenderer::class, function (CollectionRenderer $render) use ($contextId) {
            $render->tagContext = 'meerkat:responses';
            $render->setThreadId($contextId);

            return $render;
        });
    }

    /**
     * Creates an anchor link for the current comment context.
     *
     * {{ meerkat:cp-link }}
     *
     * @return string
     */
    public function cpLink()
    {
        $commentId = $this->getCurrentContextId();

        return '<a id="comment-"' . $commentId . '"></a>';
    }

    /**
     * Returns a Script element referencing Meerkat's reply JavaScript file.
     *
     * {{ meerkat:replies-to }}
     * @return string
     */
    public function repliesTo()
    {
        $scriptPath = PathProvider::publicJsVendorPath('replies-to');

        return '<script src="' . $scriptPath . '"></script>';
    }

    public function version()
    {
        return MeerkatAddon::VERSION;
    }

}
