<?php
// src/Controller/LuckyController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
//use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

use Symfony\Component\Finder\Finder;

use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;


class IndexController extends AbstractController
{
    public function index()
    {
        $jinjas = $this->getParameter('templates.jinja');
        //$configs = $this->getParameter('templates.config');

        //$files = glob($jinjas . '/*');
        //$filenames = array_map('basename', $files);
        $finder = new Finder();
        $finder->files()->in($jinjas);
        //var_dump($files);
        $filenames = array();
        foreach($finder as $file)
        {
          $dir = dirname($file->getRelativePathname());
          if(!isset($filenames[$dir]))
          {
            $filenames[$dir] = array();
          }
          $filenames[$dir][] = $file->getFilename();
        }
        return $this->render('index/list.html', array('filenames' => $filenames));
        //return new Response('', 200);
    }

    public function validateFile(Request $request)
    {
        $jinjas = $this->getParameter('templates.jinja');
        $requestedFilename = $request->request->get('file');
        if(!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\.-\/]+$/', $requestedFilename))
        {
            return new Response('error, request filename "'.$requestedFilename . '" not found', 404);
        }
        return $this->redirectToRoute('gen', array('file' => $requestedFilename));
    }

    public function showFile(Request $request)
    {
      $jinjas = $this->getParameter('templates.jinja');
      $configs = $this->getParameter('templates.config');

      $formData = $request->request->get('form');
      $requestedFilename = $request->attributes->get('file');

      if(!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\.-\/]+$/', $requestedFilename))
      {
        return new Response('show: error, request filename "'.$requestedFilename . '" not found', 404);
      }

      //if(!file_exists($jinjas . '/' . $requestedFilename))
      //{
      //  return new Response('Template file not found', 404);
      //}
      $finder = new Finder();
      $finder->in($jinjas . '/' . dirname($requestedFilename))->name(basename($requestedFilename));
      $files = iterator_to_array($finder, false);
      if(count($files) != 1)
      {
        return new Response('Template file not found', 404);
      }
      //var_dump($files);

      $configFilename = str_replace($files[0]->getExtension(), 'yaml', $requestedFilename);

      $finderConfig = new Finder();
      $finderConfig->in($configs . '/' . dirname($requestedFilename))->name(basename($configFilename));
      $configs = iterator_to_array($finderConfig, false);
      //var_dump($finderConfig, $configs);
      if(count($configs) != 1)
      {
        return new Response('Config file not found', 404);
      }

      $config = Yaml::parseFile($configs[0]->getPathname());
      //var_dump($config);
      $formBuilder = $this->createFormBuilder();

      if(!isset($config['vars']))
      {
        throw new \Exception('Config file '.$configFilename.' does not contains "vars:"');
      }

      $templateVars = array();
      foreach($config['vars'] as $key => $varData)
      {
        $templateVars[$key] = $varData['default'];
        switch($varData['type'])
        {
          case 'int':
            $type = IntegerType::class;
            $formBuilder->add($key, $type, array('data' => $varData['default']));
            break;
          case 'string':
            $type = TextType::class;
            $required = $varData['required'] ?? true;
            $options = array('data' => $varData['default'], 'required' => $required);
            if(isset($varData['help']))
            {
              $options['help'] = $varData['help'];
            }
            $formBuilder->add($key, $type, $options);
            break;
          case 'select':
            $type = ChoiceType::class;
            $choices = array_combine($varData['values'], $varData['values']);
            $options = array('choices' => $choices);
            if(isset($varData['default']))
            {
              $options['data'] = $varData['default'];
            }
            $formBuilder->add($key, $type, $options);
            break;
          case 'password':
            $type = TextType::class;
            $password = '';
            $passwordHash = '';
            exec('pwgen 20 1', $password);
            exec('echo "'.$password[0].'" | openssl passwd -stdin -6', $passwordHash);

            $formBuilder->add($key,           $type, array('data' => $password[0]));
            $formBuilder->add($key . '_hash', HiddenType::class, array('data' => $passwordHash[0]));
            $templateVars[$key . '_hash'] = $passwordHash[0];
            break;
          default:
            throw new \Exception('Type '.$varData['type'].' not managed');
        }
      }
      $formBuilder->add('file', HiddenType::class, array('data' => $requestedFilename));
      $formBuilder->add('Submit', SubmitType::class);
      $form = $formBuilder->getForm();

      $template = file_get_contents('../templates' . '/' . $jinjas . '/' . $requestedFilename);

      return $this->render(
        'index/template.html',
        array(
          'config' => $config,
          'form' => $form->createView(),
          'render' => $template,
        )
      );
    }

    public function generateFile(Request $request)
    {
      $jinjas = $this->getParameter('templates.jinja');
      $configs = $this->getParameter('templates.config');

      $formData = $request->request->get('form');
      $requestedFilename = $request->attributes->get('file');

      if(!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\.-\/]+$/', $requestedFilename))
      {
        return new Response('GENERATE: error, request filename "'.$requestedFilename . '" not found', 404);
      }

      //if(!file_exists($jinjas . '/' . $requestedFilename))
      //{
      //  return new Response('Template file not found', 404);
      //}
      $finder = new Finder();
      $finder->in($jinjas . '/' . dirname($requestedFilename))->name(basename($requestedFilename));
      $files = iterator_to_array($finder, false);
      if(count($files) != 1)
      {
        return new Response('Template file not found', 404);
      }
      //var_dump($files);

      $configFilename = str_replace($files[0]->getExtension(), 'yaml', $requestedFilename);

      $finderConfig = new Finder();
      $finderConfig->in($configs . '/' . dirname($requestedFilename))->name(basename($configFilename));
      $configs = iterator_to_array($finderConfig, false);
      //var_dump($finderConfig, $configs);
      if(count($configs) != 1)
      {
        return new Response('Config file not found', 404);
      }

      $config = Yaml::parseFile($configs[0]->getPathname());
      //var_dump($config);
      $formBuilder = $this->createFormBuilder();

      if(!isset($config['vars']))
      {
        throw new \Exception('Config file '.$configFilename.' does not contains "vars:"');
      }

      $templateVars = array();
      foreach($config['vars'] as $key => $varData)
      {
        $templateVars[$key] = $varData['default'];
        switch($varData['type'])
        {
          case 'int':
            $type = IntegerType::class;
            $formBuilder->add($key, $type, array('data' => $varData['default']));
            break;
          case 'string':
            $type = TextType::class;
            $required = $varData['required'] ?? true;
            $options = array('data' => $varData['default'], 'required' => $required);
            if(isset($varData['help']))
            {
              $options['help'] = $varData['help'];
            }
            $formBuilder->add($key, $type, $options);
            break;
          case 'select':
            $type = ChoiceType::class;
            $choices = array_combine($varData['values'], $varData['values']);
            $options = array('choices' => $choices);
            if(isset($varData['default']))
            {
              $options['data'] = $varData['default'];
            }
            $formBuilder->add($key, $type, $options);
            break;
          case 'password':
            $type = TextType::class;
            $password = '';
            $passwordHash = '';
            exec('pwgen 20 1', $password);
            exec('echo "'.$password[0].'" | openssl passwd -stdin -6', $passwordHash);

            $formBuilder->add($key,           $type, array('data' => $password[0]));
            $formBuilder->add($key . '_hash', HiddenType::class, array('data' => $passwordHash[0]));
            $templateVars[$key . '_hash'] = $passwordHash[0];
            break;
          default:
            throw new \Exception('Type '.$varData['type'].' not managed');
        }
      }
      $formBuilder->add('file', HiddenType::class, array('data' => $requestedFilename));
      $formBuilder->add('Submit', SubmitType::class);
      $form = $formBuilder->getForm();


      $form->handleRequest($request);

      if ($form->isSubmitted() && $form->isValid()) {
          // data is an array with "name", "email", and "message" keys
          $data = $form->getData();
          //var_dump('submitted');
          //var_dump($data);
          $templateVars = array_merge($templateVars, $data);
      }

      $render = $this->render(str_replace('../templates', '', $jinjas . '/' . $requestedFilename), $templateVars);

      return $this->render(
        'index/template.html',
        array(
          'config' => $config,
          'form' => $form->createView(),
          'render' => $render->getContent()
        )
      );
    }
}
