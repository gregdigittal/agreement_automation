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
        DOMAIN         = 'ccrs-sandbox.digittal.mobi'
        PMA_DOMAIN     = 'ccrs-pma-sandbox.digittal.mobi'

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

                        // Create app secrets (APP_KEY etc.)
                        sh """
                            kubectl create secret generic app-secrets \
                                --from-literal=APP_KEY='base64:PCtuu05VVFmJFB11V8GhcxccRHw4268l9hv61XwTMo8=' \
                                -n ${NAMESPACE} --dry-run=client -o yaml | kubectl apply -f -
                        """

                        // Clean up orphaned production-style resources from previous deploys
                        sh """
                            kubectl delete deployment ccrs-app ccrs-queue-worker ccrs-ai-worker -n ${NAMESPACE} --ignore-not-found=true
                            kubectl delete service ccrs-app -n ${NAMESPACE} --ignore-not-found=true
                            kubectl delete hpa ccrs-app ccrs-app-hpa -n ${NAMESPACE} --ignore-not-found=true
                            kubectl delete pdb ccrs-app -n ${NAMESPACE} --ignore-not-found=true
                            kubectl delete ingress ccrs-ingress -n ${NAMESPACE} --ignore-not-found=true
                        """

                        // Apply k8s manifests from the repo (deploy/k8s/ directory)
                        sh """
                            export APP_NAME="${APP_NAME}"
                            export IMAGE_TAG="${IMAGE_TAG}"
                            export NAMESPACE="${NAMESPACE}"
                            export DOMAIN="${DOMAIN}"
                            export PMA_DOMAIN="${PMA_DOMAIN}"

                            # Process and apply each manifest template
                            for f in deploy/k8s/*.yaml; do
                                envsubst < "\$f" | kubectl apply -n ${NAMESPACE} -f -
                            done
                        """

                        // Verify rollout
                        sh """
                            kubectl rollout status deployment/${APP_NAME} \
                                -n ${NAMESPACE} --timeout=300s
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
            emailext(
                subject: "DEPLOYED: ${APP_NAME} #${BUILD_NUMBER}",
                body: """${APP_NAME} has been deployed successfully.

App: https://${DOMAIN}
phpMyAdmin: https://${PMA_DOMAIN}
Build: ${BUILD_URL}
Branch: ${env.BRANCH_NAME ?: 'main'}
Commit: ${env.GIT_COMMIT?.take(8) ?: 'unknown'}

This is an automated notification from Jenkins.""",
                to: 'greg@digittal.io, mike@digittal.io',
                from: 'support.system@icecash.co.zw'
            )
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
                            echo "--- Current container logs ---"
                            kubectl logs deployment/${APP_NAME} -c ${APP_NAME} \
                                -n ${NAMESPACE} --tail=50 2>/dev/null || true
                            echo "--- Previous container logs (crash reason) ---"
                            kubectl logs deployment/${APP_NAME} -c ${APP_NAME} \
                                -n ${NAMESPACE} --tail=50 --previous 2>/dev/null || true
                        """
                    }
                } catch (e) {
                    echo "Could not fetch debug logs: ${e.message}"
                }
                echo "Build or deploy failed for ${APP_NAME}"
            }
            emailext(
                subject: "FAILED: ${APP_NAME} #${BUILD_NUMBER}",
                body: """${APP_NAME} deployment FAILED.

Build: ${BUILD_URL}
Branch: ${env.BRANCH_NAME ?: 'main'}
Commit: ${env.GIT_COMMIT?.take(8) ?: 'unknown'}

Check the build console output for details: ${BUILD_URL}console

This is an automated notification from Jenkins.""",
                to: 'greg@digittal.io, mike@digittal.io',
                from: 'support.system@icecash.co.zw'
            )
        }
    }
}
