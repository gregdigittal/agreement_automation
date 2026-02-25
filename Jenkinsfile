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

        AI_WORKER_IMAGE     = "${REGISTRY}/sandbox/ccrs-ai-worker"
        AI_WORKER_IMAGE_TAG = "${AI_WORKER_IMAGE}:${BUILD_NUMBER}"

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
        timeout(time: 25, unit: 'MINUTES')
        disableConcurrentBuilds()
    }

    stages {
        stage('Build Docker Image') {
            when { branch 'sandbox' }
            steps {
                script {
                    echo "Building ${IMAGE_TAG} ..."
                    def app = docker.build("${IMAGE_TAG}", ".")
                    app.push()
                    app.push('latest')

                    echo "Building AI Worker ${AI_WORKER_IMAGE_TAG} ..."
                    def aiWorker = docker.build("${AI_WORKER_IMAGE_TAG}", "./ai-worker")
                    aiWorker.push()
                    aiWorker.push('latest')
                }
            }
        }

        stage('Deploy to Kubernetes') {
            when { branch 'sandbox' }
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

                        // Create Azure AD secrets
                        sh """
                            kubectl create secret generic azure-ad-secrets \
                                --from-literal=client-secret='T.s8Q~YjGnNz59aOh6ZKgxo-MzoiIy1lH~rbnb5k' \
                                -n ${NAMESPACE} --dry-run=client -o yaml | kubectl apply -f -
                        """

                        // Create AI Worker secrets
                        sh """
                            kubectl create secret generic ai-worker-secrets \
                                --from-literal=anthropic-api-key='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' \
                                --from-literal=worker-secret='923509a7319d402e2fbc0579676b963ef3480fefcb0cea8595d456acfbb5a39f' \
                                -n ${NAMESPACE} --dry-run=client -o yaml | kubectl apply -f -
                        """

                        // Apply k8s manifests from the repo (deploy/k8s/ directory)
                        sh """
                            export APP_NAME="${APP_NAME}"
                            export IMAGE_TAG="${IMAGE_TAG}"
                            export NAMESPACE="${NAMESPACE}"
                            export DOMAIN="${DOMAIN}"
                            export PMA_DOMAIN="${PMA_DOMAIN}"
                            export AI_WORKER_IMAGE_TAG="${AI_WORKER_IMAGE_TAG}"

                            # Process and apply each manifest template
                            for f in deploy/k8s/*.yaml; do
                                envsubst < "\$f" | kubectl apply -n ${NAMESPACE} -f -
                            done
                        """

                        // Verify rollout (generous timeout — entrypoint runs migrations + seeders)
                        sh """
                            kubectl rollout status deployment/${APP_NAME} \
                                -n ${NAMESPACE} --timeout=600s
                        """
                    }
                }
            }
        }

        stage('Show App Logs') {
            when { branch 'sandbox' }
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
            script {
                if (env.BRANCH_NAME == 'sandbox') {
                    echo "Deployed successfully: https://${DOMAIN}"
                    emailext(
                        mimeType: 'text/html',
                        subject: "DEPLOYED: ${APP_NAME} #${BUILD_NUMBER}",
                        body: """<p><strong>${APP_NAME}</strong> has been deployed successfully.</p>
<table style="border-collapse:collapse; margin:12px 0;">
  <tr><td style="padding:4px 12px 4px 0; color:#666;">App</td><td><a href="https://${DOMAIN}">https://${DOMAIN}</a></td></tr>
  <tr><td style="padding:4px 12px 4px 0; color:#666;">phpMyAdmin</td><td><a href="https://${PMA_DOMAIN}">https://${PMA_DOMAIN}</a></td></tr>
  <tr><td style="padding:4px 12px 4px 0; color:#666;">Build</td><td><a href="${BUILD_URL}">#${BUILD_NUMBER}</a></td></tr>
  <tr><td style="padding:4px 12px 4px 0; color:#666;">Branch</td><td>${env.BRANCH_NAME ?: 'sandbox'}</td></tr>
  <tr><td style="padding:4px 12px 4px 0; color:#666;">Commit</td><td><code>${env.GIT_COMMIT?.take(8) ?: 'unknown'}</code></td></tr>
</table>
<p style="color:#999; font-size:12px;">Automated notification from Jenkins</p>""",
                        to: 'greg@digittal.io, mike@digittal.io',
                        from: 'support.system@icecash.co.zw'
                    )
                }
            }
        }
        failure {
            script {
                if (env.BRANCH_NAME == 'sandbox') {
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
                    emailext(
                        mimeType: 'text/html',
                        subject: "FAILED: ${APP_NAME} #${BUILD_NUMBER}",
                        body: """<p><strong>${APP_NAME}</strong> deployment <span style="color:red;">FAILED</span>.</p>
<table style="border-collapse:collapse; margin:12px 0;">
  <tr><td style="padding:4px 12px 4px 0; color:#666;">Build</td><td><a href="${BUILD_URL}">#${BUILD_NUMBER}</a></td></tr>
  <tr><td style="padding:4px 12px 4px 0; color:#666;">Console</td><td><a href="${BUILD_URL}console">View output</a></td></tr>
  <tr><td style="padding:4px 12px 4px 0; color:#666;">Branch</td><td>${env.BRANCH_NAME ?: 'sandbox'}</td></tr>
  <tr><td style="padding:4px 12px 4px 0; color:#666;">Commit</td><td><code>${env.GIT_COMMIT?.take(8) ?: 'unknown'}</code></td></tr>
</table>
<p style="color:#999; font-size:12px;">Automated notification from Jenkins</p>""",
                        to: 'greg@digittal.io, mike@digittal.io',
                        from: 'support.system@icecash.co.zw'
                    )
                }
            }
        }
    }
}
