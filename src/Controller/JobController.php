<?php

namespace App\Controller;

use App\Entity\Job;
use App\Entity\JoblangScript;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class JobController extends AbstractController
{
    #[Route('/add-job/{code}', name: 'add_job')]
    public function addJob(string $code, EntityManagerInterface $em): Response
    {

        $script = new JoblangScript();
        $script->setScript('// Shared script');

        $job = new Job();
        $job->setCode('J2024-001');

        $script->addJob($job);

        $job2 = new Job();
        $job2->setCode('J2024-002');

        $script->addJob($job2);

        $em->persist($script);
        $em->persist($job);
        $em->persist($job2);
        $em->flush();

//        $job = new Job();
//        $job->setCode($code);
//
//        $script = new JoblangScript();
//        $script->setScript('// JobLang script content here');
//
//        // Link both sides of the relation
//        $job->setJoblangScript($script); // this also sets $script->setJob($job) internally
//
//        $em->persist($job);
//        $em->flush();

        return new Response("Job added with ID " . $job->getId());
    }

    #[Route('/list-jobs', name: 'list_jobs')]
    public function listJobs(EntityManagerInterface $em): Response
    {
        $repo = $em->getRepository(Job::class);
        $jobs = $repo->findAll();

        dump($jobs);
        die();

        $codes = array_map(fn($p) => $p->getCode(), $jobs);

        return new Response("Codes: " . implode(', ', $codes));
    }

    #[Route('/delete-job/{id}', name: 'delete_job')]
    public function deleteJob(int $id, EntityManagerInterface $em): Response
    {

        $repo = $em->getRepository(Job::class);
        $job = $repo->find($id);

        $em->remove($job);
        $em->flush();

        return new Response("OK");
    }
}