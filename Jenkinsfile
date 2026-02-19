pipeline {
    agent any

    environment {
        // Auto-detect app name from the repo directory name
        APP_NAME       = "${env.JOB_NAME.split('/')[0]}"
        REGISTRY       = 'repo-de.digittal.mobi'
        IMAGE          = "${REGISTRY}/sandbox/${APP_NAME}"
        IMAGE_TAG      = "${IMAGE}:${BUILD_NUMBER}"
        IMAGE_LATEST   = "${IMAGE}:latest"
        NAMESPACE      = 'cco-sandbox'
        DOMAIN         = "${APP_NAME}-sandbox.digittal.mobi"

        // Cluster credentials
        KUBE_CREDENTIALS_ID = 'sandbox-kubeconfig'
        KUBE_SERVER_URL     = 'https://136.243.89.211:6443'
    }

    triggers {
        // GitHub webhook trigger — fires on push
        githubPush()
    }

    options {
        buildDiscarder(logRotator(numToKeepStr: '10'))
        timeout(time: 15, unit: 'MINUTES')
        disableConcurrentBuilds()
    }

    stages {
        stage('Build Docker Image') {
            steps {
                script {
                    echo "Building ${IMAGE_TAG} ..."
                    def app = docker.build("${IMAGE_TAG}", ".")
                    app.push()
                    app.push('latest')
                }
            }
        }

        stage('Deploy to Kubernetes') {
            steps {
                script {
                    withKubeConfig([credentialsId: KUBE_CREDENTIALS_ID, serverUrl: KUBE_SERVER_URL]) {
                        // Create namespace if it doesn't exist
                        sh """
                            kubectl create namespace ${NAMESPACE} --dry-run=client -o yaml | kubectl apply -f -
                        """

                        // Ensure Docker registry secret exists for image pulls
                        sh """
                            kubectl create secret docker-registry dockerreg \
                                --docker-server=repo-de.digittal.mobi \
                                --docker-username=admin \
                                --docker-password='ic3c@shz1m' \
                                -n ${NAMESPACE} --dry-run=client -o yaml | kubectl apply -f -
                        """

                        // Apply k8s manifests from the repo (deploy/k8s/ directory)
                        sh """
                            export APP_NAME="${APP_NAME}"
                            export IMAGE_TAG="${IMAGE_TAG}"
                            export NAMESPACE="${NAMESPACE}"
                            export DOMAIN="${DOMAIN}"

                            # Process and apply each manifest template
                            for f in deploy/k8s/*.yaml; do
                                envsubst < "\$f" | kubectl apply -n ${NAMESPACE} -f -
                            done
                        """

                        // Verify rollout
                        sh """
                            kubectl rollout status deployment/${APP_NAME} \
                                -n ${NAMESPACE} --timeout=180s
                        """
                    }
                }
            }
        }

        stage('Show App Logs') {
            steps {
                script {
                    withKubeConfig([credentialsId: KUBE_CREDENTIALS_ID, serverUrl: KUBE_SERVER_URL]) {
                        // Stream app container logs for 30s so the dev can see
                        // artisan migrate output, boot messages, etc.
                        sh """
                            echo "========================================="
                            echo "  Application startup logs (30s window)"
                            echo "========================================="
                            timeout 30 kubectl logs -f deployment/${APP_NAME} \
                                -c ${APP_NAME} -n ${NAMESPACE} --tail=100 2>/dev/null || true
                            echo "========================================="
                            echo "  Pod status"
                            echo "========================================="
                            kubectl get pods -n ${NAMESPACE} -l app=${APP_NAME} -o wide
                        """
                    }
                }
            }
        }
    }

    post {
        success {
            echo "Deployed successfully: https://${DOMAIN}"
        }
        failure {
            script {
                // On failure, try to show pod events and logs for debugging
                try {
                    withKubeConfig([credentialsId: KUBE_CREDENTIALS_ID, serverUrl: KUBE_SERVER_URL]) {
                        sh """
                            echo "========================================="
                            echo "  DEBUG: Pod events and logs"
                            echo "========================================="
                            kubectl get events -n ${NAMESPACE} --sort-by='.lastTimestamp' | tail -20 || true
                            echo "---"
                            kubectl logs deployment/${APP_NAME} -c ${APP_NAME} \
                                -n ${NAMESPACE} --tail=50 2>/dev/null || true
                        """
                    }
                } catch (e) {
                    echo "Could not fetch debug logs: ${e.message}"
                }
                echo "Build or deploy failed for ${APP_NAME}"
            }
        }
    }
}
