import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { formatDate } from '@/lib/dates';

export type TrendPoint = {
    date: string;
    present: number;
    late: number;
    absent: number;
    leave: number;
};

/**
 * Status colours (good / warning / critical) plus a neutral for leave. Identity never rests on colour alone:
 * the legend names every series and the tooltip lists them in words.
 */
const SERIES = [
    { key: 'present', label: 'Present', color: '#0ca30c' },
    { key: 'late', label: 'Late', color: '#fab219' },
    { key: 'leave', label: 'On leave', color: '#8a8f98' },
    { key: 'absent', label: 'Absent', color: '#d03b3b' },
] as const;

export function AttendanceTrendChart({ data }: { data: TrendPoint[] }) {
    return (
        <ResponsiveContainer width="100%" height={260}>
            <BarChart
                data={data}
                margin={{ top: 8, right: 8, left: -16, bottom: 0 }}
                barCategoryGap={2}
            >
                <CartesianGrid
                    vertical={false}
                    stroke="currentColor"
                    strokeOpacity={0.08}
                />
                <XAxis
                    dataKey="date"
                    tickFormatter={(value: string) => value.slice(8)}
                    tickLine={false}
                    axisLine={false}
                    fontSize={11}
                    stroke="currentColor"
                    strokeOpacity={0.5}
                    interval="preserveStartEnd"
                />
                <YAxis
                    allowDecimals={false}
                    tickLine={false}
                    axisLine={false}
                    fontSize={11}
                    stroke="currentColor"
                    strokeOpacity={0.5}
                />
                <Tooltip
                    cursor={{ fill: 'currentColor', fillOpacity: 0.05 }}
                    labelFormatter={(value: unknown) =>
                        typeof value === 'string' ? formatDate(value) : ''
                    }
                    contentStyle={{ borderRadius: 8, fontSize: 12 }}
                />
                <Legend
                    iconType="circle"
                    iconSize={8}
                    wrapperStyle={{ fontSize: 12 }}
                />
                {SERIES.map((series, index) => (
                    <Bar
                        key={series.key}
                        dataKey={series.key}
                        name={series.label}
                        stackId="status"
                        fill={series.color}
                        stroke="var(--background)"
                        strokeWidth={1}
                        radius={index === SERIES.length - 1 ? [4, 4, 0, 0] : 0}
                        maxBarSize={18}
                    />
                ))}
            </BarChart>
        </ResponsiveContainer>
    );
}
