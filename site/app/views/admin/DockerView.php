<?php

namespace app\views\admin;

use app\libraries\Utils;
use app\views\AbstractView;

class DockerView extends AbstractView {
    public function displayDockerPage(array $docker_data) {

        $this->output->addBreadcrumb("Docker Interface");
        $this->output->setPageName('Docker Interface');

        $this->core->getOutput()->addInternalCss('docker_interface.css');
        $this->core->getOutput()->addInternalJs('docker_interface.js');
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->enableMobileViewport();

        $found_images = [];
        $not_found = [];
        $autograding_containers = $docker_data['autograding_containers']['default'];

        //make data more human readable
        $copy = [];
        foreach ($docker_data['docker_images'] as $image) {
            $full_name = $image['tags'][0];
            $parts = explode(":", $full_name);
            $date = \DateTime::createFromFormat('Y-m-d\TH:i:s+', $image['created'])->format("Y-m-d H:i:s");
            $image["name"] = $parts[0];
            $image["tag"] = $parts[1];
            $image["created"] = $date;
            $image["size"] = Utils::formatBytes('mb', $image["size"], true);
            $image["virtual_size"] = Utils::formatBytes('mb', $image["virtual_size"], true);
            $image["additional_names"] = array_slice($image['tags'], 1);

            $copy[$full_name] = $image;
            foreach ($image["additional_names"] as $additional_name) {
                $copy[$additional_name] = $image;
            }
        }

        $docker_data['docker_images'] = $copy;

        //figure out which images are installed and listed in the config
        foreach ($docker_data['docker_images'] as $image) {
            foreach ($autograding_containers as $container) {
                if (in_array($container, $image['tags'])) {
                    $found_images[] = $image;
                    break;
                }
            }
        }

        //figure out which images are listed in the config but not found
        foreach ($autograding_containers as $autograding_image) {
            $found = false;
            foreach ($docker_data['docker_images'] as $image) {
                $name = $image['tags'][0];

                if (in_array($autograding_image, $image['tags'])) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $not_found[] = $autograding_image;
            }
        }


        //sort containers alphabetically
        $sort_containers = function (array $containers, string $key, int $order = SORT_ASC): array {
            $names = array_column($containers, $key);
            array_multisort($names, $order, $containers);
            return $containers;
        };


        $autograding_containers = [
            "found" => $sort_containers($found_images, 'name'),
            "all_images" => $sort_containers($docker_data['docker_images'], 'name'),
            "not_found" => sort($not_found)
        ];

        $capabilities = [];
        $worker_machines = [];
        $no_container_capabilities = [];
        foreach ($docker_data['autograding_workers'] as $name => $worker) {
            $worker_temp = [];
            $worker_temp['name'] = $name;
            $worker_temp['capabilities'] = [];
            $worker_temp['images'] = [];
            $image_names = [];
            foreach ($worker['capabilities'] as $capability) {
                $capabilities[] = $capability;
                $worker_temp['num_autograding_workers'] = $worker['num_autograding_workers'];
                $worker_temp['enabled'] = $worker['enabled'];
                $worker_temp['capabilities'] = $worker['capabilities'];
                // list of capabilities without containers
                $worker_temp['images_not_found'] = [];
                if (array_key_exists($capability, $docker_data['autograding_containers'])) {
                    foreach ($docker_data['autograding_containers'][$capability] as $image) {
                        $image_names[] = $image;
                    }
                }
                else {
                    $no_container_capabilities[] = $capability;
                }
            }
            // Get ride of duplicate images
            $image_names = array_unique($image_names);
            foreach ($image_names as $image) {
                // Extra precaution since data is quite messy
                if (array_key_exists($image, $autograding_containers['all_images'])) {
                    $worker_temp['images'][] = $autograding_containers['all_images'][$image];
                }
                else {
                    $worker_temp['images_not_found'][] = $image;
                }
            }
            $worker_machines[] = $worker_temp;
        }

        $capabilities = array_unique($capabilities);
        asort($capabilities);

        foreach ($worker_machines as $worker) {
            foreach ($capabilities as $capability) {
                $worker_temp['capabilities'][] = in_array($capability, $worker['capabilities']);
            }
        }

        $no_container_capabilities = array_unique($no_container_capabilities);
        return $this->output->renderTwigTemplate(
            "admin/Docker.twig",
            [
                "autograding_containers" => $autograding_containers,
                "docker_info" => $docker_data['docker_info'],
                "capabilities" => $capabilities,
                "worker_machines" => $worker_machines,
                "no_container_capabilities" => $no_container_capabilities
            ]
        );
    }
}
