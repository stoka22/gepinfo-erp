//import Alpine from 'alpinejs'

document.addEventListener('alpine:init', () => {
    Alpine.data('timeEntriesCalendar', ({ feedUrl, markersUrl = null }) => ({
        showSunday: true,
        showSaturday: true,
        showHolidays: false,

        filters: {
            vacation: true,
            sick_leave: true,
            overtime: true,
            presence: false,
        },

        feedUrl,
        markersUrl,

        selectedCompany: 'all',

        cal: null,
        holidaySet: new Set(),

        mount() {
            this.init()
        },

        init() {
            const calendarEl = this.$refs.cal
            if (!calendarEl) return

            this.cal = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'hu',
                height: 340,

                events: async (fetchInfo, successCallback, failureCallback) => {
                    try {
                        const url = new URL(this.feedUrl, window.location.origin)

                        url.searchParams.set('start', fetchInfo.startStr)
                        url.searchParams.set('end', fetchInfo.endStr)
                        url.searchParams.set('company_id', this.selectedCompany)

                        url.searchParams.set('vacation', this.filters.vacation ? '1' : '0')
                        url.searchParams.set('sick_leave', this.filters.sick_leave ? '1' : '0')
                        url.searchParams.set('overtime', this.filters.overtime ? '1' : '0')
                        url.searchParams.set('presence', this.filters.presence ? '1' : '0')

                        const response = await fetch(url.toString(), {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        })

                        if (!response.ok) {
                            throw new Error(`Calendar feed error: ${response.status}`)
                        }

                        const data = await response.json()
                        successCallback(data)
                    } catch (error) {
                        console.error(error)
                        failureCallback(error)
                    }
                },

                datesSet: (info) => {
                    this.recomputeHolidaysForRange(info.start, info.end)
                },
            })

            this.cal.render()
        },

        refetchEvents() {
            if (this.cal) {
                this.cal.refetchEvents()
            }
        },

        selectedCompanyLabel() {
            if (this.selectedCompany === 'all') {
                return 'Mind'
            }

            const select = this.$el.querySelector('select[x-model="selectedCompany"]')
            if (!select) return 'Mind'

            const option = select.options[select.selectedIndex]
            return option ? option.text : 'Mind'
        },

        recomputeHolidaysForRange(start, end) {
            // a meglévő logikád maradjon itt
        },
    }))
})