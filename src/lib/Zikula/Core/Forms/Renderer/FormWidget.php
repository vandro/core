<?php

namespace Zikula\Core\Forms\Renderer;

use Symfony\Component\Form\FormView;
use Zikula\Core\Forms\RendererInterface;
use Zikula\Core\Forms\FormRenderer;

/**
 *
 */
class FormWidget implements RendererInterface
{
    public function getName()
    {
        return 'form_widget';
    }
    
    public function render(FormView $form, $variables, FormRenderer $renderer)
    {
        $html = '<div ' . $renderer->getRender('container_attributes')->render($form, $variables, $renderer) . '>'
              . $renderer->getRender('field_rows')->render($form, $variables, $renderer)
              . $renderer->renderRest(array('form' => $form))
              . '</div>';
        
        return $html;
    }
}
