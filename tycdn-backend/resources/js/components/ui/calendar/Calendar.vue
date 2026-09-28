<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import {
    CalendarRoot,
    CalendarHeader,
    CalendarHeading,
    CalendarPrev,
    CalendarNext,
    CalendarGrid,
    CalendarGridHead,
    CalendarGridBody,
    CalendarGridRow,
    CalendarHeadCell,
    CalendarCell,
    CalendarCellTrigger,
    useForwardPropsEmits,
} from 'reka-ui';
import type { CalendarRootProps, CalendarRootEmits } from 'reka-ui';
const props = defineProps<CalendarRootProps>();
const emit = defineEmits<CalendarRootEmits>();
const forwarded = useForwardPropsEmits(props, emit);
</script>
<template>
    <CalendarRoot
        v-slot="{ grid, weekDays }"
        v-bind="forwarded"
        data-slot="calendar"
        locale="zh-CN"
        class="p-3"
        prevent-deselect
    >
        <CalendarHeader class="mb-3 flex items-center justify-between gap-2">
            <CalendarPrev
                aria-label="上个月"
                class="inline-flex size-8 items-center justify-center rounded-md border hover:bg-accent disabled:opacity-40"
                ><ChevronLeft class="size-4"
            /></CalendarPrev>
            <CalendarHeading class="text-sm font-medium" />
            <CalendarNext
                aria-label="下个月"
                class="inline-flex size-8 items-center justify-center rounded-md border hover:bg-accent disabled:opacity-40"
                ><ChevronRight class="size-4"
            /></CalendarNext>
        </CalendarHeader>
        <div class="flex flex-wrap gap-3">
            <CalendarGrid
                as="div"
                role="grid"
                v-for="month in grid"
                :key="month.value.toString()"
                class="grid"
            >
                <CalendarGridHead as="div"
                    ><CalendarGridRow
                        as="div"
                        role="row"
                        class="grid grid-cols-7"
                        ><CalendarHeadCell
                            as="div"
                            role="columnheader"
                            v-for="day in weekDays"
                            :key="day"
                            class="flex size-9 items-center justify-center text-xs font-normal text-muted-foreground"
                            >{{ day }}</CalendarHeadCell
                        ></CalendarGridRow
                    ></CalendarGridHead
                >
                <CalendarGridBody as="div"
                    ><CalendarGridRow
                        as="div"
                        role="row"
                        class="grid grid-cols-7"
                        v-for="(week, index) in month.rows"
                        :key="index"
                        ><CalendarCell
                            as="div"
                            v-for="day in week"
                            :key="day.toString()"
                            :date="day"
                            class="p-0.5"
                            ><CalendarCellTrigger
                                :day="day"
                                :month="month.value"
                                data-slot="calendar-day"
                                class="inline-flex size-8 items-center justify-center rounded-md text-sm outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring data-[disabled]:pointer-events-none data-[disabled]:opacity-30 data-[outside-view]:text-muted-foreground data-[selected]:bg-primary data-[selected]:text-primary-foreground data-[today]:font-bold" /></CalendarCell></CalendarGridRow
                ></CalendarGridBody>
            </CalendarGrid>
        </div>
    </CalendarRoot>
</template>
