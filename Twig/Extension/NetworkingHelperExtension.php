<?php
namespace Networking\InitCmsBundle\Twig\Extension;

use Networking\InitCmsBundle\Entity\LayoutBlock;
use Networking\InitCmsBundle\Twig\TokenParser\JSTokenParser;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Twig Extension for TinyMce support.
 *
 * @author naydav <web@naydav.com>
 */
class NetworkingHelperExtension extends \Twig_Extension
{
    /**
     * Container
     *
     * @var ContainerInterface
     */
    protected $container;

    protected $captureLock = false;

    protected $collectedHtml = array();

    /**
     * Initialize networking cms helper
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Gets a service.
     *
     * @param string $id The service identifier
     *
     * @return object The associated service
     */
    public function getService($id)
    {
        return $this->container->get($id);
    }

    /**
     * Get parameters from the service container
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getParameter($name)
    {
        return $this->container->getParameter($name);
    }

    /**
     * Returns the token parser instance to add to the existing list.
     *
     * @return array An array of Twig_TokenParser instances
     */
    public function getTokenParsers()
    {
        return array(
            // {% jsblock %}
            new JSTokenParser(),
        );
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return array(
            'networking_init_cms_block' => new \Twig_Function_Method($this, 'networking_init_cms_block', array('is_safe' => array('html'))),
            'networking_init_cms_get_template_zones' => new \Twig_Function_Method($this, 'networking_init_cms_get_template_zones', array('is_safe' => array('html'))),
            'networking_init_cms_get_field_zone' => new \Twig_Function_Method($this, 'networking_init_cms_get_field_zone', array('is_safe' => array('html'))),
            'networking_init_cms_sort_form_children' => new \Twig_Function_Method($this, 'networking_init_cms_sort_form_children', array('is_safe' => array('html'))),
            'networking_admin_cms_block' => new \Twig_Function_Method($this, 'networking_admin_cms_block', array('is_safe' => array('html'))),
            'networking_init_cms_content_select' => new \Twig_Function_Method($this, 'networking_init_cms_content_select', array('is_safe' => array('html'))),
        );
    }

    /**
     * Returns an HTML block for output in the frontend
     *
     * @param $template
     * @param $class
     * @param $id
     * @return mixed
     */
    public function networking_init_cms_block($template, LayoutBlock $content)
    {
        $contentItem = $this->getService('doctrine')->getRepository($content->getClassType())->find($content->getObjectId());
        $options = $contentItem->getTemplateOptions();

        return $this->getService('templating')->render($template, $options);
    }

    /**
     * Returns an HTML block for output in the admin area
     *
     * @param $class
     * @param $id
     * @return mixed
     */
    public function networking_admin_cms_block(LayoutBlock $content)
    {
        if ($content->getObjectId()) {
            $contentItem = $this->getService('doctrine')->getRepository($content->getClassType())->find($content->getObjectId());
        } else {
            $classType = $content->getClassType();
            $contentItem = new $classType();
        }
        $adminContent = $contentItem->getAdminContent();

        return $this->getService('templating')->render($adminContent['template'], $adminContent['content']);
    }

    public function networking_init_cms_get_template_zones()
    {
        return $this->getZonesByTemplate($this->getCurrentTemplate());
    }

    public function networking_init_cms_content_select()
    {
        return $this->container->getParameter('networking_init_cms.page.content_types');
    }

    public function getCurrentTemplate()
    {
        $request = $this->container->get('request');

        $pageId = (!$request->get('objectId')) ? $request->get('id') : $request->get('objectId');

        $template = null;

        $repository = $this->container->get('Doctrine')->getRepository('NetworkingInitCmsBundle:Page');

        if ($pageId) {
            $page = $repository->find($pageId);
            $template = $page->getTemplate();
        } else {
            $templates = $this->container->getParameter('networking_init_cms.page.templates');
            $template = key($templates);
        }

        if ($request->getMethod() === 'POST') {
            $uniqid = $request->get('uniqid');
            $postVars = $request->request->get($uniqid);
            $template = $postVars['template'];
        }

        if (is_null($template)) {
            return array('Please Select Template first');
        }

        return $template;
    }

    public function getZoneNames()
    {
        $zones = array();

        $template = $this->getCurrentTemplate();

        foreach ($this->getZonesByTemplate($template) as $zone) {
            $zones[] = $zone['name'];
        }

        return $zones;
    }

    /**
     * @param $template
     * @return mixed
     */
    protected function getZonesByTemplate($template)
    {
        $templates = $this->container->getParameter('networking_init_cms.page.templates');

        return $templates[$template]['zones'];
    }

    public function networking_init_cms_get_field_zone(\Symfony\Component\Form\FormView $formView)
    {
        $zones = $this->getZoneNames();

        if ($layoutBlock = $formView->vars['value']) {

            if ($zone = $layoutBlock->getZone()) {
                if (in_array($zone, $zones)) {
                    return $zone;
                }
            }
        }

        return current($zones);
    }

    public function networking_init_cms_sort_form_children($formChildren, $zone)
    {
        $zones = array();

        foreach ($formChildren as $subForms) {
            if ($this->networking_init_cms_get_field_zone($subForms) == $zone) {
                $zones[] = $subForms;
            }
        }

        return $zones;
    }

    public function addToBottom()
    {
        if ($this->captureLock) {
            throw new BadFunctionCallException('Cannot nest onLoad captures');
        }

        $this->captureLock = true;
        \ob_start();
    }

    public function addToBottomEnd()
    {
        $data = \ob_get_clean();
        $this->captureLock = false;
        $this->addToCollectedHtml($data);
//        return true;
    }

    protected function addToCollectedHtml($data)
    {
        $this->collectedHtml[] = $data;
//		return $this;
    }

    public function render()
    {
        return implode("\n    ", $this->collectedHtml) . "\n";
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'networking_init_cms_helper';
    }
}
