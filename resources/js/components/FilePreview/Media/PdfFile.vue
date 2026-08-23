<template>
    <div
        class="absolute bottom-0 top-0 left-0 right-0 z-10 mx-auto overflow-y-auto rounded-xl md:p-5"
        :style="{ width: documentSize + '%' }"
    >
        <div v-if="isLoading" class="fixed left-0 right-0 top-1/2 z-10 mx-auto w-full translate-y-5">
            <Spinner />
        </div>
        <div
            v-for="i in numPages"
            :key="i"
            :ref="'page-' + i"
            id="printable-file"
            class="pdf-page mx-auto mb-6 w-full overflow-hidden md:rounded-xl md:shadow-lg"
        />
    </div>
</template>

<script>
import Spinner from '../../UI/Others/Spinner'
import { events } from '../../../bus'
import { AnnotationMode, getDocument, GlobalWorkerOptions } from 'pdfjs-dist/legacy/build/pdf.mjs'
import { EventBus, PDFPageView } from 'pdfjs-dist/legacy/web/pdf_viewer.mjs'

GlobalWorkerOptions.workerSrc = new URL('pdfjs-dist/legacy/build/pdf.worker.min.mjs', import.meta.url).toString()

export default {
    name: 'PdfFile',
    components: { Spinner },
    props: ['file'],
    watch: {
        file() {
            this.getPdf()
            this.isLoading = true
        },
    },
    data() {
        return {
            pdfData: undefined,
            documentSize: 50,
            isLoading: true,
            numPages: 0,
            pdfDocument: undefined,
            pageViews: [],
            eventBus: undefined,
            loadSequence: 0,
        }
    },
    methods: {
        documentLoaded() {
            this.isLoading = false
        },
        async getPdf() {
            const loadSequence = ++this.loadSequence
            const previousLoadingTask = this.pdfData
            const previousPdfDocument = this.pdfDocument

            this.pdfData = undefined
            this.numPages = 0
            this.pdfDocument = undefined
            this.destroyPageViews()

            await Promise.resolve()
                .then(() => {
                    if (previousLoadingTask) return previousLoadingTask.destroy()
                    if (previousPdfDocument) return previousPdfDocument.destroy()
                })
                .catch(() => {})

            if (loadSequence !== this.loadSequence) return

            let loadingTask

            try {
                loadingTask = getDocument({
                    url: this.file.data.attributes.file_url,
                    enableXfa: false,
                    // User PDFs are untrusted; avoid compiling PDF function strings.
                    isEvalSupported: false,
                })
            } catch (error) {
                this.documentLoaded()
                console.error('Unable to load PDF preview.', error)
                return
            }

            this.pdfData = loadingTask

            try {
                const pdfDocument = await loadingTask.promise

                if (loadSequence !== this.loadSequence) {
                    await loadingTask.destroy()
                    return
                }

                this.pdfDocument = pdfDocument
                this.numPages = pdfDocument.numPages
                await this.$nextTick()
                await this.renderPages(loadSequence, pdfDocument)
            } catch (error) {
                if (loadSequence === this.loadSequence) {
                    this.documentLoaded()
                    console.error('Unable to render PDF preview.', error)
                }
            }
        },
        async renderPages(loadSequence, pdfDocument) {
            this.eventBus = new EventBus()

            for (let pageNumber = 1; pageNumber <= this.numPages; pageNumber += 1) {
                if (!this.isCurrentRender(loadSequence, pdfDocument)) return

                const page = await pdfDocument.getPage(pageNumber)

                if (!this.isCurrentRender(loadSequence, pdfDocument)) {
                    this.cleanupPage(page)
                    return
                }

                const pageContainer = this.$refs[`page-${pageNumber}`]
                const container = Array.isArray(pageContainer) ? pageContainer[0] : pageContainer

                if (!container) continue
                if (!this.isCurrentRender(loadSequence, pdfDocument)) {
                    this.cleanupPage(page)
                    return
                }

                const defaultViewport = page.getViewport({ scale: 1 })
                const scale = container.clientWidth / defaultViewport.width
                const pageView = new PDFPageView({
                    container,
                    id: pageNumber,
                    scale,
                    defaultViewport,
                    eventBus: this.eventBus,
                    annotationMode: AnnotationMode.DISABLE,
                })

                pageView.setPdfPage(page)
                this.pageViews.push(pageView)

                try {
                    await pageView.draw()
                } catch (error) {
                    if (this.isCurrentRender(loadSequence, pdfDocument)) throw error
                    this.destroyPageView(pageView)
                    return
                }

                if (!this.isCurrentRender(loadSequence, pdfDocument)) {
                    this.destroyPageView(pageView)
                    return
                }

                if (pageNumber === 1) this.documentLoaded()
            }
        },
        isCurrentRender(loadSequence, pdfDocument) {
            return loadSequence === this.loadSequence && this.pdfDocument === pdfDocument
        },
        async resizePages() {
            if (!this.pdfDocument || !this.pageViews.length) return

            const loadSequence = this.loadSequence
            const pdfDocument = this.pdfDocument
            const pageViews = this.pageViews.slice()

            try {
                pageViews.forEach((pageView) => {
                    const container = pageView.div.parentElement
                    const scale = container.clientWidth / pageView.pdfPage.getViewport({ scale: 1 }).width
                    pageView.update({ scale })
                })

                await Promise.all(pageViews.map((pageView) => pageView.draw()))
            } catch (error) {
                if (this.isCurrentRender(loadSequence, pdfDocument)) {
                    console.error('Unable to resize PDF preview.', error)
                }
            }
        },
        destroyPageViews() {
            this.pageViews.slice().forEach((pageView) => this.destroyPageView(pageView))
            this.pageViews = []
        },
        destroyPageView(pageView) {
            const pageViewIndex = this.pageViews.indexOf(pageView)
            if (pageViewIndex !== -1) this.pageViews.splice(pageViewIndex, 1)

            try {
                pageView.destroy()
            } catch {}
        },
        cleanupPage(page) {
            try {
                page.cleanup()
            } catch {}
        },
        getDocumentSize() {
            if (window.innerWidth < 960) {
                this.documentSize = 100
            }

            if (window.innerWidth > 960) {
                this.documentSize = localStorage.getItem('documentSize')
                    ? parseInt(localStorage.getItem('documentSize'))
                    : 50
            }
        },
        zoomIn() {
            if (this.documentSize < 100) {
                this.documentSize += 10
                localStorage.setItem('documentSize', this.documentSize)
                this.$nextTick(() => this.resizePages())
            }
        },
        zoomOut() {
            if (this.documentSize > 40) {
                this.documentSize -= 10
                localStorage.setItem('documentSize', this.documentSize)
                this.$nextTick(() => this.resizePages())
            }
        },
    },
    created() {
        this.getDocumentSize()
        this.getPdf()

        events.$on('document-zoom:in', this.zoomIn)
        events.$on('document-zoom:out', this.zoomOut)
    },
    mounted() {
        window.addEventListener('resize', this.resizePages)
    },
    beforeDestroy() {
        window.removeEventListener('resize', this.resizePages)
        this.loadSequence += 1
        this.destroyPageViews()
        Promise.resolve()
            .then(() => {
                if (this.pdfData) return this.pdfData.destroy()
                if (this.pdfDocument) return this.pdfDocument.destroy()
            })
            .catch(() => {})
        events.$off('document-zoom:in', this.zoomIn)
        events.$off('document-zoom:out', this.zoomOut)
    },
}
</script>

<style src="pdfjs-dist/web/pdf_viewer.css" lang="css"></style>
